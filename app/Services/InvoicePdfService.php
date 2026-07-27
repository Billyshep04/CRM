<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoicePdfService
{
    private const TEMPLATE_VERSION = 6;

    public function __construct(private readonly AdminInvoiceSettings $invoiceSettings)
    {
    }

    public function generate(Invoice $invoice): StoredFile
    {
        $invoice->loadMissing(['customer', 'lineItems.billable']);

        if ($invoice->pdfFile && $this->isCurrentTemplate($invoice->pdfFile)) {
            return $invoice->pdfFile;
        }

        $safeNumber = Str::slug($invoice->invoice_number);
        $fileName = "{$safeNumber}.pdf";
        $path = "invoices/{$invoice->id}/{$fileName}";

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'payment_details' => $this->invoiceSettings->paymentDetails(),
            'invoice_logo_data_uri' => $this->invoiceLogoDataUri(),
            'invoice_stamp_data_uri' => $this->invoiceStampDataUri(),
        ]);

        $contents = $pdf->output();
        $disk = 'private';

        Storage::disk($disk)->put($path, $contents);

        if ($invoice->pdfFile) {
            $invoice->pdfFile->forceFill([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $fileName,
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'checksum' => hash('sha256', $contents),
                'metadata' => ['invoice_template_version' => self::TEMPLATE_VERSION],
            ])->save();

            return $invoice->pdfFile->fresh();
        }

        return StoredFile::create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size' => strlen($contents),
            'category' => 'invoice_pdf',
            'checksum' => hash('sha256', $contents),
            'is_private' => true,
            'metadata' => ['invoice_template_version' => self::TEMPLATE_VERSION],
            'uploaded_by_user_id' => $invoice->created_by_user_id,
            'owner_type' => $invoice::class,
            'owner_id' => $invoice->id,
        ]);
    }

    private function fileExists(StoredFile $file): bool
    {
        if (!is_string($file->disk) || $file->disk === '') {
            return false;
        }

        if (!is_string($file->path) || $file->path === '') {
            return false;
        }

        return Storage::disk($file->disk)->exists($file->path);
    }

    private function isCurrentTemplate(StoredFile $file): bool
    {
        return $this->fileExists($file)
            && (int) data_get($file->metadata, 'invoice_template_version') === self::TEMPLATE_VERSION;
    }

    private function invoiceLogoDataUri(): ?string
    {
        $logoPath = resource_path('images/white-logo2.svg');
        if (!is_file($logoPath)) {
            return null;
        }

        $contents = file_get_contents($logoPath);
        if ($contents === false || $contents === '') {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }

    private function invoiceStampDataUri(): ?string
    {
        $logoPath = resource_path('images/white-logo2.svg');
        if (!is_file($logoPath)) {
            return null;
        }

        $contents = file_get_contents($logoPath);
        if (
            $contents === false
            || preg_match('/<image[^>]+xlink:href="(data:image\/png;base64,[^"]+)"/', $contents, $matches) !== 1
        ) {
            return null;
        }

        return $matches[1];
    }
}
