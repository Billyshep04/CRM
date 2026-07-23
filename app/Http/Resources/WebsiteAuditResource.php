<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'website_id' => $this->website_id,
            'business_id' => $this->business_id,
            'version' => $this->version,
            'status' => $this->status->value,
            'requested_url' => $this->requested_url,
            'final_url' => $this->final_url,
            'http_status' => $this->http_status,
            'http_version' => $this->http_version,
            'scores' => [
                'overall' => $this->overall_score,
                'seo' => $this->seo_score,
                'performance' => $this->performance_score,
                'accessibility' => $this->accessibility_score,
                'security' => $this->security_score,
            ],
            'redirect_chain' => $this->redirect_chain,
            'results' => $this->when($this->status->value === 'completed', $this->structured_results),
            'failure' => $this->when($this->status->value === 'failed', [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
            ]),
            'findings' => AuditFindingResource::collection($this->whenLoaded('findings')),
            'seo' => $this->whenLoaded('seoAudit', fn () => $this->seoAudit ? [
                'score' => $this->seoAudit->score, 'meta_title' => $this->seoAudit->meta_title,
                'meta_description' => $this->seoAudit->meta_description, 'canonical_url' => $this->seoAudit->canonical_url,
                'heading_count' => $this->seoAudit->heading_count, 'image_count' => $this->seoAudit->image_count,
                'images_missing_alt' => $this->seoAudit->images_missing_alt, 'internal_link_count' => $this->seoAudit->internal_link_count,
                'broken_link_count' => $this->seoAudit->broken_link_count, 'has_sitemap' => $this->seoAudit->has_sitemap,
                'has_robots_txt' => $this->seoAudit->has_robots_txt, 'schema_item_count' => $this->seoAudit->schema_item_count,
            ] : null),
            'performance' => $this->whenLoaded('performanceAudit', fn () => $this->performanceAudit ? [
                'score' => $this->performanceAudit->score, 'page_size_bytes' => $this->performanceAudit->page_size_bytes,
                'request_count' => $this->performanceAudit->request_count, 'response_time_ms' => $this->performanceAudit->response_time_ms,
            ] : null),
            'accessibility' => $this->whenLoaded('accessibilityAudit', fn () => $this->accessibilityAudit ? [
                'score' => $this->accessibilityAudit->score, 'images_missing_alt' => $this->accessibilityAudit->images_missing_alt,
                'empty_link_count' => $this->accessibilityAudit->empty_link_count,
                'unlabelled_form_control_count' => $this->accessibilityAudit->unlabelled_form_control_count,
                'html_language' => $this->accessibilityAudit->html_language,
            ] : null),
            'security' => $this->whenLoaded('securityAudit', fn () => $this->securityAudit ? [
                'score' => $this->securityAudit->score, 'uses_https' => $this->securityAudit->uses_https,
                'ssl_valid' => $this->securityAudit->ssl_valid, 'server_technology' => $this->securityAudit->server_technology,
                'hosting_provider' => $this->securityAudit->hosting_provider, 'has_hsts' => $this->securityAudit->has_hsts,
                'has_csp' => $this->securityAudit->has_csp, 'has_frame_protection' => $this->securityAudit->has_frame_protection,
            ] : null),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
        ];
    }
}
