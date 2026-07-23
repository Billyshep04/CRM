<?php

namespace Tests\Feature;

use App\Contracts\LeadScoreRepository;
use App\Contracts\LeadScoringEngine;
use App\Enums\LeadScoreFactor;
use App\Models\Business;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;
use App\Services\LeadScoring\DefaultLeadScoringProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_strong_google_presence_and_a_poor_website_produce_the_highest_priority(): void
    {
        $profile = app(DefaultLeadScoringProfile::class)->resolve();
        $engine = app(LeadScoringEngine::class);

        $strongBusiness = $this->business(['google_rating' => 4.8, 'google_review_count' => 500]);
        $weakBusiness = $this->business(['google_rating' => 2.0, 'google_review_count' => 3]);
        $goodWebsiteBusiness = $this->business(['google_rating' => 4.8, 'google_review_count' => 500, 'design_quality_score' => 90, 'professionalism_score' => 90, 'missing_features' => []]);

        $strongPoor = $engine->calculate($strongBusiness, $this->audit($strongBusiness, 25), $profile);
        $weakPoor = $engine->calculate($weakBusiness, $this->audit($weakBusiness, 25), $profile);
        $strongGood = $engine->calculate($goodWebsiteBusiness, $this->audit($goodWebsiteBusiness, 90), $profile);

        $this->assertGreaterThan($weakPoor['score'], $strongPoor['score']);
        $this->assertGreaterThan($strongGood['score'], $strongPoor['score']);
        $this->assertSame('hot', $strongPoor['grade']);
        $this->assertArrayHasKey('google_rating', $strongPoor['breakdown']);
        $this->assertArrayHasKey('broken_links', $strongPoor['breakdown']);
    }

    public function test_custom_weights_change_the_score_without_hiding_the_calculation(): void
    {
        $business = $this->business(['google_rating' => 4.5]);
        $audit = $this->audit($business, 50);
        $profile = LeadScoringProfile::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'Google only', 'version' => 1]);
        $profile->weights()->create(['factor' => LeadScoreFactor::GoogleRating, 'weight' => 100, 'is_enabled' => true]);

        $result = app(LeadScoringEngine::class)->calculate($business, $audit, $profile);

        $this->assertSame(90.0, $result['score']);
        $this->assertSame(100.0, $result['confidence']);
        $this->assertSame(90.0, $result['breakdown']['google_rating']['opportunity_score']);
    }

    public function test_each_recalculation_is_stored_as_history_and_only_one_score_is_current(): void
    {
        $business = $this->business();
        $profile = app(DefaultLeadScoringProfile::class)->resolve();
        $audit = $this->audit($business, 35);
        $engine = app(LeadScoringEngine::class);
        $repository = app(LeadScoreRepository::class);

        $repository->store($business, $audit, $profile, $engine->calculate($business, $audit, $profile));
        $business->update(['google_review_count' => 900]);
        $repository->store($business->fresh(), $audit, $profile, $engine->calculate($business->fresh(), $audit, $profile));

        $this->assertDatabaseCount('lead_scores', 2);
        $this->assertSame(1, $business->leadScores()->where('is_current', true)->count());
        $this->assertSame(1, $business->leadScores()->where('is_current', false)->count());
        $this->assertSame($business->leadScores()->where('is_current', true)->value('score'), $business->fresh()->lead_score);
    }

    private function business(array $overrides = []): Business
    {
        return Business::query()->create(array_merge([
            'public_id' => (string) Str::ulid(), 'name' => fake()->company(), 'website_url' => 'https://example.com',
            'google_rating' => 4.2, 'google_review_count' => 100, 'domain_registered_at' => now()->subYears(8),
            'design_quality_score' => 25, 'professionalism_score' => 25,
            'missing_features' => ['booking', 'testimonials', 'calls_to_action', 'analytics', 'schema'],
        ], $overrides));
    }

    private function audit(Business $business, float $quality): WebsiteAudit
    {
        $audit = WebsiteAudit::query()->create([
            'public_id' => (string) Str::ulid(), 'business_id' => $business->id, 'version' => 1, 'status' => 'completed',
            'requested_url' => $business->website_url, 'performance_score' => $quality, 'seo_score' => $quality,
            'accessibility_score' => $quality, 'security_score' => $quality,
            'structured_results' => ['mobile' => ['score' => $quality], 'marketing' => ['analytics' => ['detected' => false]], 'seo' => ['schema' => ['present' => false]]],
            'completed_at' => now(),
        ]);
        $audit->seoAudit()->create(['score' => $quality, 'broken_link_count' => 6, 'details' => []]);
        $audit->performanceAudit()->create(['score' => $quality, 'details' => []]);
        $audit->accessibilityAudit()->create(['score' => $quality, 'details' => []]);
        $audit->securityAudit()->create(['score' => $quality, 'uses_https' => $quality >= 50, 'ssl_valid' => $quality >= 50, 'details' => []]);

        return $audit->fresh();
    }
}
