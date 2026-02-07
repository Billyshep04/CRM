<?php

namespace App\Jobs;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail implements ShouldQueue
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
        $invoice = Invoice::query()
            ->with(['customer', 'pdfFile', 'lineItems'])
            ->findOrFail($this->invoiceId);

        if (!$invoice->customer) {
            return;
        }

        if (!$invoice->pdfFile) {
            $storedFile = $pdfService->generate($invoice);
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
            $invoice->setRelation('pdfFile', $storedFile);
        }

        Mail::to($invoice->customer->email)
            ->send(new InvoiceMailable($invoice));
    }
}
