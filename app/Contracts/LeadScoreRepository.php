<?php

namespace App\Contracts;

use App\Models\Business;
use App\Models\LeadScore;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;

interface LeadScoreRepository
{
    /** @param array{score: float, grade: string, confidence: float, breakdown: array<string, mixed>, input_snapshot: array<string, mixed>} $result */
    public function store(Business $business, WebsiteAudit $audit, LeadScoringProfile $profile, array $result, ?int $userId = null): LeadScore;
}
