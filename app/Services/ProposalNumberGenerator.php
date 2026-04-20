<?php

namespace App\Services;

use App\Models\Proposal;
use Illuminate\Support\Str;

class ProposalNumberGenerator
{
    public function generate(): string
    {
        do {
            $candidate = 'PROP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Proposal::query()->where('proposal_number', $candidate)->exists());

        return $candidate;
    }
}
