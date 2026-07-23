<?php

namespace App\Contracts;

use App\Models\Business;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;

interface LeadScoringEngine
{
    /** @return array{score: float, grade: string, confidence: float, breakdown: array<string, mixed>, input_snapshot: array<string, mixed>} */
    public function calculate(Business $business, WebsiteAudit $audit, LeadScoringProfile $profile): array;
}
