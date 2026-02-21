<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';
    protected $description = 'Generate invoices for active subscriptions that are due.';

    public function handle(RecurringInvoiceService $recurringInvoiceService): int
    {
        $autoSend = (bool) config('invoices.auto_send_recurring', true);
        $result = $recurringInvoiceService->processDueSubscriptions(null, $autoSend);

        if ($result['created'] === 0) {
            $this->info('No subscriptions are due.');
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
