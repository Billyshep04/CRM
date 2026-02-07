<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
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
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:customers,user_id'],
        ]);

        $customer = Customer::create([
            ...$validated,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return new CustomerResource($customer);
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
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($customer->id),
            ],
        ]);

        $customer->update($validated);

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }
}
