<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring {--reconcile-all : Process all active subscriptions and repair stale next invoice dates}';
    protected $description = 'Generate invoices for active subscriptions that are due.';

    public function handle(RecurringInvoiceService $recurringInvoiceService): int
    {
        $autoSend = (bool) config('invoices.auto_send_recurring', true);
        $reconcileAll = (bool) $this->option('reconcile-all');
        $result = $recurringInvoiceService->processDueSubscriptions(null, $autoSend, null, $reconcileAll);

        if ($result['created'] === 0) {
            $this->info($reconcileAll ? 'No subscriptions needed reconciliation.' : 'No subscriptions are due.');
            return self::SUCCESS;
        }

        $message = "Created {$result['created']} recurring invoice(s).";
        if ($autoSend) {
            $message .= " Sent {$result['sent']} invoice(s).";
            if ($result['failed'] > 0) {
                $message .= " Failed {$result['failed']} invoice(s).";
            }
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
