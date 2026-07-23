<?php

namespace App\Services\LeadScoring;

use App\Contracts\LeadScoringEngine;
use App\Enums\LeadScoreFactor;
use App\Models\Business;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;

class WeightedLeadScoringEngine implements LeadScoringEngine
{
    public function calculate(Business $business, WebsiteAudit $audit, LeadScoringProfile $profile): array
    {
        $audit->loadMissing(['seoAudit', 'performanceAudit', 'accessibilityAudit', 'securityAudit']);
        $profile->loadMissing('weights');
        $results = $audit->structured_results ?? [];
        $inputs = $this->inputs($business, $audit, $results);
        $weights = $profile->weights->where('is_enabled', true)->mapWithKeys(fn ($weight) => [$weight->factor->value => (float) $weight->weight]);
        $totalWeight = max(0.001, (float) $weights->sum());
        $knownWeight = 0.0;
        $totalPoints = 0.0;
        $breakdown = [];

        foreach (LeadScoreFactor::cases() as $factor) {
            $weight = (float) ($weights[$factor->value] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            $input = $inputs[$factor->value] ?? null;
            $opportunity = $input === null ? 0.0 : $this->opportunity($factor, $input);
            $points = $opportunity * $weight / 100;
            if ($input !== null) {
                $knownWeight += $weight;
            }
            $totalPoints += $points;
            $breakdown[$factor->value] = [
                'input' => $input,
                'opportunity_score' => round($opportunity, 2),
                'weight' => $weight,
                'weighted_points' => round($points, 3),
                'available' => $input !== null,
                'direction' => in_array($factor, [LeadScoreFactor::WebsiteAge, LeadScoreFactor::GoogleRating, LeadScoreFactor::GoogleReviewCount], true) ? 'business_strength' : 'website_need',
            ];
        }

        $score = round(max(0, min(100, $totalPoints / $totalWeight * 100)), 2);

        return [
            'score' => $score,
            'grade' => match (true) {
                $score >= 75 => 'hot', $score >= 55 => 'warm', $score >= 35 => 'cool', default => 'cold'
            },
            'confidence' => round($knownWeight / $totalWeight * 100, 2),
            'breakdown' => $breakdown,
            'input_snapshot' => $inputs,
        ];
    }

    /** @return array<string, mixed> */
    private function inputs(Business $business, WebsiteAudit $audit, array $results): array
    {
        $registeredAt = $business->domain_registered_at;

        return [
            LeadScoreFactor::WebsiteAge->value => $registeredAt ? round($registeredAt->diffInDays(now()) / 365.25, 2) : null,
            LeadScoreFactor::Performance->value => $audit->performance_score !== null ? (float) $audit->performance_score : null,
            LeadScoreFactor::Seo->value => $audit->seo_score !== null ? (float) $audit->seo_score : null,
            LeadScoreFactor::Accessibility->value => $audit->accessibility_score !== null ? (float) $audit->accessibility_score : null,
            LeadScoreFactor::Security->value => $audit->security_score !== null ? (float) $audit->security_score : null,
            LeadScoreFactor::GoogleRating->value => $business->google_rating !== null ? (float) $business->google_rating : null,
            LeadScoreFactor::GoogleReviewCount->value => $business->google_review_count,
            LeadScoreFactor::WebsiteDesign->value => $business->design_quality_score !== null ? (float) $business->design_quality_score : null,
            LeadScoreFactor::MissingFeatures->value => is_array($business->missing_features) ? count($business->missing_features) : null,
            LeadScoreFactor::BrokenLinks->value => $audit->seoAudit?->broken_link_count,
            LeadScoreFactor::MobileFriendliness->value => data_get($results, 'mobile.score'),
            LeadScoreFactor::Https->value => $audit->securityAudit ? ($audit->securityAudit->uses_https && $audit->securityAudit->ssl_valid ? 100 : 0) : null,
            LeadScoreFactor::Analytics->value => data_get($results, 'marketing.analytics.detected') === null ? null : (data_get($results, 'marketing.analytics.detected') ? 100 : 0),
            LeadScoreFactor::Schema->value => data_get($results, 'seo.schema.present') === null ? null : (data_get($results, 'seo.schema.present') ? 100 : 0),
            LeadScoreFactor::Professionalism->value => $business->professionalism_score !== null ? (float) $business->professionalism_score : null,
        ];
    }

    private function opportunity(LeadScoreFactor $factor, float|int $input): float
    {
        return max(0, min(100, match ($factor) {
            LeadScoreFactor::WebsiteAge => ((float) $input / 10) * 100,
            LeadScoreFactor::GoogleRating => ((float) $input / 5) * 100,
            LeadScoreFactor::GoogleReviewCount => log10(max(0, (float) $input) + 1) / 3 * 100,
            LeadScoreFactor::MissingFeatures => ((float) $input / 8) * 100,
            LeadScoreFactor::BrokenLinks => ((float) $input / 10) * 100,
            default => 100 - (float) $input,
        }));
    }
}
