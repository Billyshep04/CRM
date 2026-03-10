<?php

namespace App\Services;

use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecurringInvoiceService
{
    private const DUE_PROCESS_LOCK_KEY = 'recurring-invoices:process-due';
    private const DUE_PROCESS_LOCK_SECONDS = 120;
    private const DUE_PROCESS_WAIT_SECONDS = 5;

    public function __construct(private readonly InvoiceNumberGenerator $numberGenerator)
    {
    }

    /**
     * @return array{created:int,sent:int,failed:int}
     */
    public function processDueSubscriptions(
        ?int $subscriptionId = null,
        bool $autoSend = true,
        ?array $customerIds = null
    ): array {
        $normalizedCustomerIds = $this->normalizeCustomerIds($customerIds);
        if ($customerIds !== null && $normalizedCustomerIds === []) {
            return $this->emptyResult();
        }

        try {
            return Cache::lock(self::DUE_PROCESS_LOCK_KEY, self::DUE_PROCESS_LOCK_SECONDS)
                ->block(
                    self::DUE_PROCESS_WAIT_SECONDS,
                    fn (): array => $this->processDueSubscriptionsWithoutLock(
                        $subscriptionId,
                        $autoSend,
                        $normalizedCustomerIds
                    )
                );
        } catch (LockTimeoutException) {
            return $this->emptyResult();
        }
    }

    /**
     * @param  array<int>|null  $customerIds
     * @return array{created:int,sent:int,failed:int}
     */
    private function processDueSubscriptionsWithoutLock(
        ?int $subscriptionId = null,
        bool $autoSend = true,
        ?array $customerIds = null
    ): array
    {
        $today = now()->startOfDay();

        $query = Subscription::query()->where('status', 'active');

        if ($subscriptionId !== null) {
            $query->whereKey($subscriptionId);
        }

        if ($customerIds !== null) {
            $query->whereIn('customer_id', $customerIds);
        }

        if ($this->shouldFilterToDueSubscriptions($subscriptionId, $customerIds)) {
            $query->where(function ($query) use ($today): void {
                $query->whereDate('next_invoice_date', '<=', $today->toDateString())
                    ->orWhere(function ($subQuery) use ($today): void {
                        $subQuery->whereNull('next_invoice_date')
                            ->whereDate('start_date', '<=', $today->toDateString());
                    });
            });
        }

        $subscriptions = $query->get();

        $createdCount = 0;
        $sentCount = 0;
        $failedCount = 0;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->start_date) {
                continue;
            }

            $billingDayOfMonth = (int) $subscription->start_date->day;
            $nextInvoiceDate = $this->resolveInitialNextInvoiceDate($subscription, $billingDayOfMonth);
            $createdInvoices = [];

            while ($nextInvoiceDate->lte($today)) {
                if (!$this->subscriptionInvoiceExistsForDate($subscription, $nextInvoiceDate)) {
                    $createdInvoices[] = DB::transaction(function () use ($subscription, $nextInvoiceDate): Invoice {
                        $invoiceNumber = $this->numberGenerator->generate();
                        $issueDate = $nextInvoiceDate->toDateString();
                        $dueDate = $nextInvoiceDate->copy()->addDays(14)->toDateString();

                        $invoice = Invoice::create([
                            'customer_id' => $subscription->customer_id,
                            'created_by_user_id' => $subscription->created_by_user_id,
                            'invoice_number' => $invoiceNumber,
                            'issue_date' => $issueDate,
                            'due_date' => $dueDate,
                            'status' => 'draft',
                            'subtotal' => $subscription->monthly_cost,
                            'tax_amount' => 0,
                            'total' => $subscription->monthly_cost,
                        ]);

                        InvoiceLineItem::create([
                            'invoice_id' => $invoice->id,
                            'billable_type' => Subscription::class,
                            'billable_id' => $subscription->id,
                            'description' => $subscription->description,
                            'quantity' => 1,
                            'unit_price' => $subscription->monthly_cost,
                            'total' => $subscription->monthly_cost,
                        ]);

                        return $invoice;
                    });

                    $createdCount++;
                }

                $nextInvoiceDate = $this->nextBillingDate($nextInvoiceDate, $billingDayOfMonth);
            }

            $resolvedNextInvoiceDate = $nextInvoiceDate->toDateString();
            if ($subscription->next_invoice_date?->toDateString() !== $resolvedNextInvoiceDate) {
                $subscription->update([
                    'next_invoice_date' => $resolvedNextInvoiceDate,
                ]);
            }

            if (!$autoSend) {
                continue;
            }

            foreach ($createdInvoices as $invoice) {
                try {
                    SendInvoiceEmail::dispatchSync($invoice->id);
                    $invoice->forceFill([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ])->save();
                    $sentCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failedCount++;
                }
            }
        }

        return [
            'created' => $createdCount,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ];
    }

    /**
     * If a specific subscription/customer scope is supplied, process all active subscriptions
     * in that scope so stale future next_invoice_date values can still be corrected.
     *
     * @param  array<int>|null  $customerIds
     */
    private function shouldFilterToDueSubscriptions(?int $subscriptionId, ?array $customerIds): bool
    {
        return $subscriptionId === null && $customerIds === null;
    }

    private function emptyResult(): array
    {
        return [
            'created' => 0,
            'sent' => 0,
            'failed' => 0,
        ];
    }

    /**
     * @param  array<mixed>|null  $customerIds
     * @return array<int>|null
     */
    private function normalizeCustomerIds(?array $customerIds): ?array
    {
        if ($customerIds === null) {
            return null;
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $customerIds),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function resolveInitialNextInvoiceDate(Subscription $subscription, int $billingDayOfMonth): Carbon
    {
        $nextDateFromField = $subscription->next_invoice_date?->copy()->startOfDay();
        $latestIssuedInvoiceDate = $this->resolveLatestIssuedSubscriptionInvoiceDate($subscription);

        if ($latestIssuedInvoiceDate) {
            $nextDateFromInvoiceHistory = $this->nextBillingDate($latestIssuedInvoiceDate, $billingDayOfMonth);

            if (!$nextDateFromField) {
                return $nextDateFromInvoiceHistory;
            }

            return $nextDateFromField->lt($nextDateFromInvoiceHistory)
                ? $nextDateFromField
                : $nextDateFromInvoiceHistory;
        }

        if ($nextDateFromField) {
            return $nextDateFromField;
        }

        return $subscription->start_date->copy()->startOfDay();
    }

    private function resolveLatestIssuedSubscriptionInvoiceDate(Subscription $subscription): ?Carbon
    {
        $latestInvoice = Invoice::query()
            ->where('customer_id', $subscription->customer_id)
            ->whereHas('lineItems', function ($query) use ($subscription): void {
                $query->where('billable_type', Subscription::class)
                    ->where('billable_id', $subscription->id);
            })
            ->orderByDesc('issue_date')
            ->first(['issue_date']);

        return $latestInvoice?->issue_date?->copy()->startOfDay();
    }

    private function nextBillingDate(Carbon $currentBillingDate, int $billingDayOfMonth): Carbon
    {
        $nextMonth = $currentBillingDate->copy()->startOfMonth()->addMonthNoOverflow();
        $day = min($billingDayOfMonth, $nextMonth->daysInMonth);

        return $nextMonth->setDay($day)->startOfDay();
    }

    private function subscriptionInvoiceExistsForDate(Subscription $subscription, Carbon $billingDate): bool
    {
        return Invoice::query()
            ->where('customer_id', $subscription->customer_id)
            ->whereDate('issue_date', $billingDate->toDateString())
            ->whereHas('lineItems', function ($query) use ($subscription): void {
                $query->where('billable_type', Subscription::class)
                    ->where('billable_id', $subscription->id);
            })
            ->exists();
    }
}
