<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Job;

class InvoiceJobStatusSyncService
{
    public function syncFromInvoice(Invoice $invoice, string $paymentStatus): void
    {
        if ($paymentStatus !== 'paid') {
            return;
        }

        $jobIds = $invoice->lineItems()
            ->where('billable_type', Job::class)
            ->whereNotNull('billable_id')
            ->pluck('billable_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($jobIds === []) {
            return;
        }

        $jobs = Job::query()
            ->where('customer_id', $invoice->customer_id)
            ->whereIn('id', $jobIds)
            ->get();

        foreach ($jobs as $job) {
            $job->forceFill([
                'status' => 'completed',
                'completed_at' => $job->completed_at ?? now(),
            ])->save();
        }
    }
}

