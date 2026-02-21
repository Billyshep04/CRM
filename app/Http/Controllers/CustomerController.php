<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    private const DEFAULT_CUSTOMER_PASSWORD = 'WebStamp123';

    public function index(Request $request)
    {
        $this->syncCustomerPortalUsersIfNeeded();

        $query = Customer::query()
            ->withCount(['jobs', 'subscriptions'])
            ->withSum('jobs', 'cost')
            ->withSum([
                'subscriptions as subscriptions_sum_monthly_cost' => function ($builder): void {
                    $builder->where('status', 'active');
                },
            ], 'monthly_cost')
            ->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);

        return CustomerResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
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
        $customer->load(['jobs', 'subscriptions', 'websites', 'invoices']);

        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
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
}
