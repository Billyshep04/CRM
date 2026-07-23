<?php

namespace App\Repositories;

use App\Contracts\LeadScoreRepository;
use App\Models\Business;
use App\Models\LeadScore;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentLeadScoreRepository implements LeadScoreRepository
{
    public function store(Business $business, WebsiteAudit $audit, LeadScoringProfile $profile, array $result, ?int $userId = null): LeadScore
    {
        return DB::transaction(function () use ($business, $audit, $profile, $result, $userId): LeadScore {
            $business->leadScores()->where('is_current', true)->lockForUpdate()->update(['is_current' => false]);
            $score = $business->leadScores()->create([
                'public_id' => (string) Str::ulid(),
                'website_audit_id' => $audit->id,
                'lead_scoring_profile_id' => $profile->id,
                'calculated_by_user_id' => $userId,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'confidence' => $result['confidence'],
                'breakdown' => $result['breakdown'],
                'input_snapshot' => $result['input_snapshot'],
                'is_current' => true,
                'calculated_at' => now(),
            ]);
            $business->update(['lead_score' => $result['score'], 'lead_grade' => $result['grade'], 'lead_scored_at' => now()]);

            return $score;
        });
    }
}
