<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceNumberGenerator
{
    public function generate(): string
    {
        do {
            $candidate = 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Invoice::query()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
