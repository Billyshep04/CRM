<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use App\Jobs\SendInvoiceEmail;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';
    protected $description = 'Generate invoices for active subscriptions that are due.';

    public function handle(InvoiceNumberGenerator $numberGenerator): int
    {
        $dueSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->whereDate('next_invoice_date', '<=', now()->toDateString())
            ->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No subscriptions are due.');
            return self::SUCCESS;
        }

        $createdCount = 0;

        $autoSend = (bool) config('invoices.auto_send_recurring');

        foreach ($dueSubscriptions as $subscription) {
            $createdInvoiceId = null;

            DB::transaction(function () use ($subscription, $numberGenerator, &$createdCount, &$createdInvoiceId): void {
                $invoiceNumber = $numberGenerator->generate();
                $issueDate = now()->toDateString();
                $dueDate = now()->addDays(14)->toDateString();

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

                $nextDate = $subscription->next_invoice_date ?? $subscription->start_date;
                $subscription->update([
                    'next_invoice_date' => $nextDate->copy()->addMonthNoOverflow(),
                ]);

                $createdCount++;
                $createdInvoiceId = $invoice->id;
            });

            if ($autoSend && $createdInvoiceId) {
                $invoice = Invoice::query()->find($createdInvoiceId);

                if ($invoice) {
                    $invoice->forceFill([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ])->save();
                    SendInvoiceEmail::dispatch($invoice->id);
                }
            }
        }

        $this->info("Created {$createdCount} invoice(s).");

        return self::SUCCESS;
    }
}
