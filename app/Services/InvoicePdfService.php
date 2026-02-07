<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoicePdfService
{
    public function generate(Invoice $invoice): StoredFile
    {
        $invoice->loadMissing(['customer', 'lineItems']);

        if ($invoice->pdfFile && $this->fileExists($invoice->pdfFile)) {
            return $invoice->pdfFile;
        }

        $safeNumber = Str::slug($invoice->invoice_number);
        $fileName = "{$safeNumber}.pdf";
        $path = "invoices/{$invoice->id}/{$fileName}";

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
        ]);

        $contents = $pdf->output();
        $disk = 'private';

        Storage::disk($disk)->put($path, $contents);

        return StoredFile::create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size' => strlen($contents),
            'category' => 'invoice_pdf',
            'checksum' => hash('sha256', $contents),
            'is_private' => true,
            'uploaded_by_user_id' => $invoice->created_by_user_id,
            'owner_type' => $invoice::class,
            'owner_id' => $invoice->id,
        ]);
    }

    private function fileExists(StoredFile $file): bool
    {
        return Storage::disk($file->disk)->exists($file->path);
    }
}
