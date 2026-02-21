<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use App\Jobs\SendInvoiceEmail;
use App\Services\InvoiceNumberGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';
    protected $description = 'Generate invoices for active subscriptions that are due.';

    public function handle(InvoiceNumberGenerator $numberGenerator): int
    {
        $today = now()->startOfDay();

        $dueSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereDate('next_invoice_date', '<=', $today->toDateString())
                    ->orWhere(function ($subQuery) use ($today): void {
                        $subQuery->whereNull('next_invoice_date')
                            ->whereDate('start_date', '<=', $today->toDateString());
                    });
            })
            ->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No subscriptions are due.');
            return self::SUCCESS;
        }

        $createdCount = 0;
        $sentCount = 0;

        $autoSend = (bool) config('invoices.auto_send_recurring', true);

        foreach ($dueSubscriptions as $subscription) {
            if (!$subscription->start_date) {
                continue;
            }

            $billingDayOfMonth = (int) $subscription->start_date->day;
            $nextInvoiceDate = $this->resolveInitialNextInvoiceDate($subscription);
            $createdInvoices = [];

            while ($nextInvoiceDate->lte($today)) {
                if (!$this->subscriptionInvoiceExistsForDate($subscription, $nextInvoiceDate)) {
                    $createdInvoices[] = DB::transaction(function () use ($subscription, $numberGenerator, $nextInvoiceDate): Invoice {
                        $invoiceNumber = $numberGenerator->generate();
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

            $subscription->update([
                'next_invoice_date' => $nextInvoiceDate->toDateString(),
            ]);

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
                    $this->error("Failed to send {$invoice->invoice_number}: {$exception->getMessage()}");
                }
            }
        }

        $message = "Created {$createdCount} recurring invoice(s).";
        if ($autoSend) {
            $message .= " Sent {$sentCount} invoice(s).";
        }

        $this->info($message);

        return self::SUCCESS;
    }

    private function resolveInitialNextInvoiceDate(Subscription $subscription): Carbon
    {
        if ($subscription->next_invoice_date) {
            return $subscription->next_invoice_date->copy()->startOfDay();
        }

        return $subscription->start_date->copy()->startOfDay();
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
