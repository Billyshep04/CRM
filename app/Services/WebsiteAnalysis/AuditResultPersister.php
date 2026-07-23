<?php

namespace App\Services\WebsiteAnalysis;

use App\Enums\WebsiteAuditStatus;
use App\Jobs\CalculateLeadScore;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\DB;

class AuditResultPersister
{
    /** @param array<string, mixed> $result */
    public function persist(WebsiteAudit $audit, array $result): void
    {
        DB::transaction(function () use ($audit, $result): void {
            $scores = $result['scores'];
            $homepage = $result['homepage'];
            $audit->update([
                'status' => WebsiteAuditStatus::Completed,
                'final_url' => $homepage['final_url'],
                'http_status' => $homepage['status_code'],
                'http_version' => $homepage['http_version'],
                'overall_score' => $scores['overall'],
                'seo_score' => $scores['seo'],
                'performance_score' => $scores['performance'],
                'accessibility_score' => $scores['accessibility'],
                'security_score' => $scores['security'],
                'redirect_chain' => $result['redirects'],
                'structured_results' => $result,
                'completed_at' => now(),
                'failed_at' => null,
                'failure_code' => null,
                'failure_message' => null,
            ]);

            $seo = $result['seo'];
            $audit->seoAudit()->create([
                'score' => $scores['seo'],
                'meta_title' => $seo['meta_title']['value'],
                'meta_description' => $seo['meta_description']['value'],
                'canonical_url' => $seo['canonical_url'],
                'heading_count' => collect($seo['headings'])->flatten()->count(),
                'image_count' => $seo['images']['count'],
                'images_missing_alt' => $seo['images']['missing_alt_count'],
                'internal_link_count' => $seo['internal_links']['count'],
                'broken_link_count' => $seo['broken_links']['count'],
                'has_sitemap' => $seo['sitemap']['exists'],
                'has_robots_txt' => $seo['robots_txt']['exists'],
                'schema_item_count' => $seo['schema']['item_count'],
                'details' => $seo,
            ]);

            $performance = $result['performance'];
            $audit->performanceAudit()->create([
                'score' => $scores['performance'],
                'page_size_bytes' => $performance['page_size_bytes'],
                'request_count' => $performance['request_count'],
                'response_time_ms' => $homepage['response_time_ms'],
                'details' => $performance,
            ]);

            $accessibility = $result['accessibility'];
            $audit->accessibilityAudit()->create([
                'score' => $scores['accessibility'],
                'images_missing_alt' => $accessibility['images_missing_alt'],
                'empty_link_count' => $accessibility['empty_link_count'],
                'unlabelled_form_control_count' => $accessibility['unlabelled_form_control_count'],
                'html_language' => $accessibility['html_language'],
                'details' => $accessibility,
            ]);

            $security = $result['security'];
            $technology = $result['technology'];
            $audit->securityAudit()->create([
                'score' => $scores['security'],
                'uses_https' => $security['uses_https'],
                'ssl_valid' => $security['ssl_valid'],
                'server_technology' => $technology['server'] ?? $technology['powered_by'],
                'hosting_provider' => $technology['hosting_provider'],
                'has_hsts' => $security['has_hsts'],
                'has_csp' => $security['has_csp'],
                'has_frame_protection' => $security['has_frame_protection'],
                'details' => $security + ['technology' => $technology],
            ]);

            $audit->findings()->createMany($result['findings']);
        });

        if ($audit->business_id) {
            CalculateLeadScore::dispatch($audit->business_id, $audit->id)->afterCommit();
        }
    }
}
