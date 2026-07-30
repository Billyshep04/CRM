<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;

class InvoiceNumberGenerator
{
    public function __construct(private readonly OrganisationSettings $settings) {}

    public function generate(?int $customerId = null, mixed $issueDate = null): string
    {
        if (! $customerId) {
            return $this->generateLegacyNumber();
        }

        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return $this->generateLegacyNumber();
        }

        $initials = $this->extractInitials((string) $customer->name);
        $datePart = $this->resolveDatePart($issueDate);
        $nextSequence = Invoice::query()->withTrashed()->where('customer_id', $customerId)->count() + 1;

        do {
            $candidate = "{$initials}-{$nextSequence}-{$datePart}";
            $nextSequence++;
        } while (Invoice::query()->withTrashed()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    private function generateLegacyNumber(): string
    {
        do {
            $candidate = $this->settings->all()['invoice_prefix'].'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Invoice::query()->withTrashed()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    private function extractInitials(string $name): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9\\s]/', ' ', $name);
        $tokens = preg_split('/\\s+/', trim((string) $normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) >= 2) {
            return Str::upper(substr($tokens[0], 0, 1).substr($tokens[1], 0, 1));
        }

        if (count($tokens) === 1) {
            $token = Str::upper($tokens[0]);
            if (strlen($token) >= 2) {
                return substr($token, 0, 2);
            }

            return $token.'X';
        }

        return 'IN';
    }

    private function resolveDatePart(mixed $issueDate): string
    {
        if ($issueDate instanceof DateTimeInterface) {
            return Carbon::instance($issueDate)->format('dmy');
        }

        if (is_string($issueDate) && trim($issueDate) !== '') {
            try {
                return Carbon::parse($issueDate)->format('dmy');
            } catch (\Throwable) {
                // Fallback handled below.
            }
        }

        return now()->format('dmy');
    }
}
