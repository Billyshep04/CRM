<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->invoice->loadMissing(['customer', 'lineItems', 'pdfFile']);
    }

    public function build(): self
    {
        $mail = $this->subject("Invoice {$this->invoice->invoice_number}")
            ->view('emails.invoice', [
                'invoice' => $this->invoice,
                'customer' => $this->invoice->customer,
            ]);

        if ($this->invoice->pdfFile) {
            $mail->attachFromStorageDisk(
                $this->invoice->pdfFile->disk,
                $this->invoice->pdfFile->path,
                [
                    'as' => "Invoice-{$this->invoice->invoice_number}.pdf",
                    'mime' => 'application/pdf',
                ]
            );
        }

        return $mail;
    }
}
