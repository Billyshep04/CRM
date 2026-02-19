<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionMonthResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionMonth;
use App\Models\Subscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $this->ensureSubscriptionMonthsTableExists();

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

        $status = $validated['status'] ?? 'active';

        $subscription = Subscription::create([
            ...$validated,
            'created_by_user_id' => $request->user()?->id,
            'billing_frequency' => $validated['billing_frequency'] ?? 'monthly',
            'status' => $status,
            'next_invoice_date' => $validated['next_invoice_date'] ?? $validated['start_date'],
            'paused_at' => $status === 'paused' ? ($validated['paused_at'] ?? now()) : null,
        ]);

        $this->syncSubscriptionMonths($subscription);

        return new SubscriptionResource($subscription->load('customer'));
    }

    public function show(Subscription $subscription)
    {
        $this->ensureSubscriptionMonthsTableExists();

        return new SubscriptionResource($subscription->load('customer'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->ensureSubscriptionMonthsTableExists();

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

        if (($validated['status'] ?? null) === 'paused') {
            $validated['paused_at'] = $validated['paused_at'] ?? now();
        }

        if (($validated['status'] ?? null) === 'active') {
            $validated['paused_at'] = null;
        }

        $subscription->update($validated);

        $this->syncSubscriptionMonths($subscription);

        return new SubscriptionResource($subscription->load('customer'));
    }

    public function months(Subscription $subscription)
    {
        $this->syncSubscriptionMonths($subscription);

        return SubscriptionMonthResource::collection(
            SubscriptionMonth::query()
                ->where('subscription_id', $subscription->id)
                ->orderByDesc('month_start')
                ->get()
        );
    }

    public function updateMonth(Request $request, Subscription $subscription, SubscriptionMonth $subscriptionMonth)
    {
        return $this->updateMonthPayment($request, $subscription, $subscriptionMonth);
    }

    public function updateMonthPayment(Request $request, Subscription $subscription, SubscriptionMonth $subscriptionMonth)
    {
        $this->ensureSubscriptionMonthsTableExists();

        if ($subscriptionMonth->subscription_id !== $subscription->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
        ]);

        $paymentStatus = $validated['payment_status'];
        $subscriptionMonth->update([
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? ($subscriptionMonth->paid_at ?? now()) : null,
        ]);

        return new SubscriptionMonthResource($subscriptionMonth);
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted.']);
    }

    private function syncSubscriptionMonths(Subscription $subscription): void
    {
        $this->ensureSubscriptionMonthsTableExists();

        if (!$subscription->start_date) {
            return;
        }

        $startMonth = $subscription->start_date->copy()->startOfMonth();
        $currentMonth = now()->startOfMonth();

        if ($startMonth->greaterThan($currentMonth)) {
            $startMonth = $currentMonth->copy();
        }

        $pausedFromMonth = $subscription->paused_at?->copy()->startOfMonth();

        $cursor = $startMonth->copy();
        while ($cursor->lte($currentMonth)) {
            $defaultStatus = 'active';
            if ($pausedFromMonth && $cursor->gte($pausedFromMonth)) {
                $defaultStatus = 'paused';
            }

            SubscriptionMonth::query()->firstOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'month_start' => $cursor->toDateString(),
                ],
                [
                    'subscription_status' => $defaultStatus,
                    'payment_status' => 'unpaid',
                ]
            );

            $cursor->addMonthNoOverflow();
        }

        SubscriptionMonth::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('month_start', $currentMonth->toDateString())
            ->update(['subscription_status' => $subscription->status]);
    }

    private function ensureSubscriptionMonthsTableExists(): void
    {
        if (Schema::hasTable('subscription_months') || !Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::create('subscription_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->date('month_start');
            $table->string('subscription_status')->default('active');
            $table->string('payment_status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'month_start']);
            $table->index(['subscription_id', 'month_start']);
        });
    }
}
