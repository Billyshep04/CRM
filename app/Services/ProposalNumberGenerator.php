<?php

namespace App\Services;

use App\Models\Proposal;
use Illuminate\Support\Str;

class ProposalNumberGenerator
{
    public function __construct(private readonly OrganisationSettings $settings) {}

    public function generate(): string
    {
        $prefix = $this->settings->all()['proposal_prefix'];
        do {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Proposal::query()->where('proposal_number', $candidate)->exists());

        return $candidate;
    }
}
