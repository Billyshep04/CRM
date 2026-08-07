<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerController extends Controller
{
    private const DEFAULT_CUSTOMER_PASSWORD = 'WebStamp123';

    public function index(Request $request)
    {
        $this->syncCustomerPortalUsersIfNeeded();
        $archivedOnly = $request->boolean('archived');
        $supportsArchiving = $this->supportsCustomerArchiving($archivedOnly);

        $query = Customer::query()
            ->withCount(['jobs', 'subscriptions'])
            ->withSum([
                'invoices as paid_invoices_sum_total' => function ($builder): void {
                    $builder->where('status', 'paid');
                },
            ], 'total')
            ->withSum([
                'subscriptions as subscriptions_sum_monthly_cost' => function ($builder): void {
                    $builder->where('status', 'active');
                },
            ], 'monthly_cost')
            ->latest();

        if ($supportsArchiving) {
            $query->when(
                $archivedOnly,
                static fn ($builder) => $builder->whereNotNull('archived_at'),
                static fn ($builder) => $builder->whereNull('archived_at')
            );
        } elseif ($archivedOnly) {
            $query->whereRaw('1 = 0');
        }

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);

        return CustomerResource::collection(
            $query->paginate($perPage)
        );
    }

    public function archive(Customer $customer)
    {
        if (!$this->supportsCustomerArchiving(true)) {
            throw ValidationException::withMessages([
                'archive' => ['Customer archiving is unavailable. Add the archived_at column on customers, then retry.'],
            ]);
        }

        if ($customer->archived_at === null) {
            $customer->forceFill(['archived_at' => now()])->save();
        }

        return new CustomerResource($customer->fresh());
    }

    public function unarchive(Customer $customer)
    {
        if (!$this->supportsCustomerArchiving(true)) {
            throw ValidationException::withMessages([
                'archive' => ['Customer archiving is unavailable. Add the archived_at column on customers, then retry.'],
            ]);
        }

        if ($customer->archived_at !== null) {
            $customer->forceFill(['archived_at' => null])->save();
        }

        return new CustomerResource($customer->fresh());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $this->emailRules(),
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = DB::transaction(function () use ($validated, $request): Customer {
            $customer = Customer::create([
                ...$validated,
                'created_by_user_id' => $request->user()?->id,
            ]);
            $this->ensureCustomerPortalUser($customer, true);

            return $customer;
        });

        return new CustomerResource($customer->fresh());
    }

    public function show(Customer $customer)
    {
        $supportsJobArchiving = Schema::hasColumn('jobs', 'archived_at');
        $customer->load([
            'jobs' => static fn ($query) => $query->when(
                $supportsJobArchiving,
                static fn ($jobQuery) => $jobQuery->whereNull('archived_at')
            ),
            'subscriptions',
            'websites',
            'invoices',
        ]);

        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => $this->emailRules($customer, 'sometimes'),
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'billing_address' => ['sometimes', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($customer, $validated): void {
            $customer->update($validated);
            $customer->refresh();
            $this->ensureCustomerPortalUser($customer, true);
        });

        return new CustomerResource($customer->fresh());
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    private function syncCustomerPortalUsersIfNeeded(): void
    {
        $requiresSync = Customer::query()
            ->where(function ($query): void {
                $query->whereNull('user_id')->orWhereDoesntHave('user');
            })
            ->exists();

        if (!$requiresSync) {
            return;
        }

        $customers = Customer::query()
            ->where(function ($query): void {
                $query->whereNull('user_id')->orWhereDoesntHave('user');
            })
            ->get();

        foreach ($customers as $customer) {
            $this->ensureCustomerPortalUser($customer, false);
        }
    }

    private function ensureCustomerPortalUser(Customer $customer, bool $strict): void
    {
        $customerRoleId = Role::query()->where('slug', 'customer')->value('id');
        if (!$customerRoleId) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'email' => ['Customer role is missing. Run role seeding first.'],
                ]);
            }

            return;
        }

        if (!$customer->email) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'email' => ['Customer email is required to create a portal login.'],
                ]);
            }

            return;
        }

        $portalUser = $customer->user;
        if ($portalUser && $portalUser->hasAnyRole(['admin', 'staff'])) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'email' => ['Customer email conflicts with an internal user account.'],
                ]);
            }

            return;
        }

        if (!$portalUser) {
            $portalUser = User::query()->where('email', $customer->email)->first();

            if ($portalUser && $portalUser->hasAnyRole(['admin', 'staff'])) {
                if ($strict) {
                    throw ValidationException::withMessages([
                        'email' => ['Customer email conflicts with an internal user account.'],
                    ]);
                }

                return;
            }
        }

        if (!$portalUser) {
            $portalUser = User::query()->create([
                'name' => $customer->name ?: 'Customer',
                'email' => $customer->email,
                'password' => Hash::make(self::DEFAULT_CUSTOMER_PASSWORD),
            ]);
        } else {
            $updates = [];
            if ($portalUser->name !== $customer->name && $customer->name) {
                $updates['name'] = $customer->name;
            }

            if ($portalUser->email !== $customer->email) {
                $emailInUse = User::query()
                    ->where('email', $customer->email)
                    ->where('id', '!=', $portalUser->id)
                    ->exists();

                if ($emailInUse) {
                    if ($strict) {
                        throw ValidationException::withMessages([
                            'email' => ['That email is already used by another user account.'],
                        ]);
                    }

                    return;
                }

                $updates['email'] = $customer->email;
            }

            if ($updates !== []) {
                $portalUser->update($updates);
            }
        }

        $isLinkedElsewhere = Customer::query()
            ->where('user_id', $portalUser->id)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($isLinkedElsewhere) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'email' => ['This portal user is already linked to another customer.'],
                ]);
            }

            return;
        }

        $portalUser->roles()->syncWithoutDetaching([$customerRoleId]);
        if ($customer->user_id !== $portalUser->id) {
            $customer->forceFill(['user_id' => $portalUser->id])->saveQuietly();
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function emailRules(?Customer $customer = null, string $presence = 'required'): array
    {
        return [
            $presence,
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($customer): void {
                $email = mb_strtolower(trim((string) $value));

                $customerEmailExists = Customer::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->when($customer, static fn ($query) => $query->where('id', '!=', $customer->id))
                    ->exists();

                $userEmailExists = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->when($customer?->user_id, static fn ($query, $userId) => $query->where('id', '!=', $userId))
                    ->exists();

                if ($customerEmailExists || $userEmailExists) {
                    $fail('That email address already exists. Please use a different email address.');
                }
            },
        ];
    }

    private function supportsCustomerArchiving(bool $attemptAutoCreate = false): bool
    {
        static $supportsArchiving = null;

        if ($supportsArchiving !== null) {
            return $supportsArchiving;
        }

        $supportsArchiving = Schema::hasColumn('customers', 'archived_at');
        if ($supportsArchiving || !$attemptAutoCreate) {
            return $supportsArchiving;
        }

        try {
            Schema::table('customers', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('notes');
            });
        } catch (Throwable) {
            // Ignore and keep returning false if the DB user cannot alter schema.
        }

        $supportsArchiving = Schema::hasColumn('customers', 'archived_at');

        return $supportsArchiving;
    }
}
