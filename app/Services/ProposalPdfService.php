<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProposalPdfService
{
    public function generate(Proposal $proposal): StoredFile
    {
        $proposal->loadMissing(['customer', 'job', 'lineItems']);

        if ($proposal->pdfFile && $this->fileExists($proposal->pdfFile)) {
            return $proposal->pdfFile;
        }

        $safeNumber = Str::slug($proposal->proposal_number . '-v' . $proposal->version);
        $fileName = "{$safeNumber}.pdf";
        $path = "proposals/{$proposal->id}/{$fileName}";

        $pdf = Pdf::loadView('pdf.proposal', [
            'proposal' => $proposal,
            'customer' => $proposal->customer,
            'job' => $proposal->job,
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
            'category' => 'proposal_pdf',
            'checksum' => hash('sha256', $contents),
            'is_private' => true,
            'uploaded_by_user_id' => $proposal->created_by_user_id,
            'owner_type' => $proposal::class,
            'owner_id' => $proposal->id,
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
}
