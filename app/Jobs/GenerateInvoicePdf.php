<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(InvoicePdfService $pdfService): void
    {
        $invoice = Invoice::query()->findOrFail($this->invoiceId);

        $storedFile = $pdfService->generate($invoice);

        if ($invoice->pdf_file_id !== $storedFile->id) {
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
        }
    }
}
