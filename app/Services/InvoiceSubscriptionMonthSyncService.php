<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionMonth;
use Illuminate\Support\Facades\Schema;

class InvoiceSubscriptionMonthSyncService
{
    public function syncFromInvoice(Invoice $invoice, string $paymentStatus): void
    {
        if (!Schema::hasTable('subscription_months')) {
            return;
        }

        $invoice->loadMissing('lineItems');

        $subscriptionIds = $invoice->lineItems
            ->where('billable_type', Subscription::class)
            ->pluck('billable_id')
            ->filter(static fn ($id): bool => !empty($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($subscriptionIds->isEmpty()) {
            return;
        }

        $issueDate = $invoice->issue_date?->copy() ?? now();
        $monthStart = $issueDate->copy()->startOfMonth();
        $monthEnd = $issueDate->copy()->endOfMonth();

        $subscriptions = Subscription::query()
            ->withTrashed()
            ->whereIn('id', $subscriptionIds->all())
            ->get()
            ->keyBy('id');

        foreach ($subscriptionIds as $subscriptionId) {
            $subscription = $subscriptions->get($subscriptionId);
            if (!$subscription) {
                continue;
            }

            $resolved = $this->resolvePaymentState(
                $invoice,
                $paymentStatus,
                $subscriptionId,
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            );

            $month = SubscriptionMonth::query()->firstOrCreate(
                [
                    'subscription_id' => $subscriptionId,
                    'month_start' => $monthStart->toDateString(),
                ],
                [
                    'subscription_status' => $subscription->status ?? 'active',
                    'payment_status' => $resolved['payment_status'],
                    'paid_at' => $resolved['paid_at'],
                ]
            );

            $month->update([
                'subscription_status' => $subscription->status ?? $month->subscription_status,
                'payment_status' => $resolved['payment_status'],
                'paid_at' => $resolved['paid_at'],
            ]);
        }
    }

    /**
     * @return array{payment_status:string,paid_at:mixed}
     */
    private function resolvePaymentState(
        Invoice $invoice,
        string $requestedPaymentStatus,
        int $subscriptionId,
        string $monthStart,
        string $monthEnd
    ): array {
        if ($requestedPaymentStatus === 'paid') {
            return [
                'payment_status' => 'paid',
                'paid_at' => $invoice->paid_at ?? now(),
            ];
        }

        $otherPaidInvoice = Invoice::query()
            ->whereKeyNot($invoice->id)
            ->where('status', 'paid')
            ->whereDate('issue_date', '>=', $monthStart)
            ->whereDate('issue_date', '<=', $monthEnd)
            ->whereHas('lineItems', function ($query) use ($subscriptionId): void {
                $query->where('billable_type', Subscription::class)
                    ->where('billable_id', $subscriptionId);
            })
            ->orderByDesc('paid_at')
            ->first(['paid_at']);

        if ($otherPaidInvoice) {
            return [
                'payment_status' => 'paid',
                'paid_at' => $otherPaidInvoice->paid_at ?? now(),
            ];
        }

        return [
            'payment_status' => 'unpaid',
            'paid_at' => null,
        ];
    }
}
