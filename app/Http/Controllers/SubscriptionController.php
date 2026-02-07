<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::query()->with('customer')->latest();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->integer('per_page', 15);

        return SubscriptionResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'description' => ['required', 'string'],
            'monthly_cost' => ['required', 'numeric', 'min:0'],
            'billing_frequency' => ['nullable', Rule::in(['monthly'])],
            'start_date' => ['required', 'date'],
            'next_invoice_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'paused'])],
            'paused_at' => ['nullable', 'date'],
        ]);

        $subscription = Subscription::create([
            ...$validated,
            'created_by_user_id' => $request->user()?->id,
            'billing_frequency' => $validated['billing_frequency'] ?? 'monthly',
            'status' => $validated['status'] ?? 'active',
            'next_invoice_date' => $validated['next_invoice_date'] ?? $validated['start_date'],
        ]);

        if ($subscription->status === 'paused' && !$subscription->paused_at) {
            $subscription->forceFill(['paused_at' => now()])->save();
        }

        return new SubscriptionResource($subscription->load('customer'));
    }

    public function show(Subscription $subscription)
    {
        return new SubscriptionResource($subscription->load('customer'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'description' => ['sometimes', 'string'],
            'monthly_cost' => ['sometimes', 'numeric', 'min:0'],
            'billing_frequency' => ['sometimes', Rule::in(['monthly'])],
            'start_date' => ['sometimes', 'date'],
            'next_invoice_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
            'paused_at' => ['nullable', 'date'],
        ]);

        $subscription->update($validated);

        if ($subscription->status === 'paused' && !$subscription->paused_at) {
            $subscription->forceFill(['paused_at' => now()])->save();
        }

        return new SubscriptionResource($subscription->load('customer'));
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted.']);
    }
}
