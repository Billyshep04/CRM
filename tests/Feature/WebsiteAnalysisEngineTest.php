<?php

namespace Tests\Feature;

use App\Contracts\WebsiteAnalyzer;
use App\Jobs\AnalyzeWebsite;
use App\Models\WebsiteAudit;
use App\Services\WebsiteAnalysis\AuditResultPersister;
use App\Services\WebsiteAnalysis\DeterministicWebsiteAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteAnalysisEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_structured_results_for_a_homepage(): void
    {
        config()->set('website-audits.enforce_public_networks', false);
        config()->set('website-audits.max_links_to_check', 10);

        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'HEAD') {
                return match (true) {
                    str_ends_with($url, '/broken') => Http::response('', 404),
                    str_contains($url, 'leadforge-404-check-') => Http::response('', 404),
                    default => Http::response('', 200),
                };
            }

            return Http::response(<<<'HTML'
                <!doctype html><html lang="en"><head>
                <title>Example Agency Website</title>
                <meta name="description" content="A complete description of the example agency and the services it provides to local businesses.">
                <link rel="canonical" href="/">
                <link rel="stylesheet" href="/app.css">
                <script src="https://www.googletagmanager.com/gtag/js?id=G-ABC123"></script>
                <script type="application/ld+json">{"@context":"https://schema.org","@type":"LocalBusiness"}</script>
                </head><body><h1>Example Agency</h1><h2>Services</h2>
                <img src="/hero.jpg" alt="Agency team"><img src="/work.jpg">
                <a href="/contact">Contact us</a><a href="/privacy-policy">Privacy policy</a>
                <a href="/broken">Broken</a><a href="https://linkedin.com/company/example">LinkedIn</a>
                <div class="cookie-consent">Accept all cookies</div></body></html>
                HTML, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Strict-Transport-Security' => 'max-age=31536000',
                'Content-Security-Policy' => "default-src 'self'",
                'X-Frame-Options' => 'DENY',
                'Server' => 'nginx',
                'CF-Ray' => 'test',
            ]);
        });

        $result = app(DeterministicWebsiteAnalyzer::class)->analyze('https://example.com');

        $this->assertSame('Example Agency Website', $result['seo']['meta_title']['value']);
        $this->assertSame(2, $result['seo']['images']['count']);
        $this->assertSame(1, $result['seo']['images']['missing_alt_count']);
        $this->assertSame(1, $result['seo']['broken_links']['count']);
        $this->assertTrue($result['seo']['schema']['present']);
        $this->assertTrue($result['marketing']['analytics']['detected']);
        $this->assertTrue($result['marketing']['cookie_banner']['detected']);
        $this->assertTrue($result['pages']['contact_page']['found']);
        $this->assertTrue($result['pages']['privacy_policy']['found']);
        $this->assertTrue($result['pages']['custom_404']['valid_status']);
        $this->assertSame('Cloudflare', $result['technology']['hosting_provider']);
        $this->assertNotEmpty($result['findings']);
    }

    public function test_the_queued_job_persists_an_immutable_audit_snapshot(): void
    {
        $audit = WebsiteAudit::query()->create([
            'public_id' => '01J00000000000000000000000',
            'version' => 1,
            'status' => 'pending',
            'requested_url' => 'https://example.com',
        ]);

        $result = $this->resultFixture();
        $analyzer = $this->mock(WebsiteAnalyzer::class);
        $analyzer->shouldReceive('analyze')->once()->andReturn($result);

        (new AnalyzeWebsite($audit->id))->handle($analyzer, app(AuditResultPersister::class));

        $audit->refresh();
        $this->assertSame('completed', $audit->status->value);
        $this->assertSame('88.00', $audit->overall_score);
        $this->assertNotNull($audit->structured_results);
        $this->assertDatabaseCount('seo_audits', 1);
        $this->assertDatabaseCount('performance_audits', 1);
        $this->assertDatabaseCount('accessibility_audits', 1);
        $this->assertDatabaseCount('security_audits', 1);
        $this->assertDatabaseCount('audit_findings', 1);
    }

    private function resultFixture(): array
    {
        return [
            'homepage' => ['final_url' => 'https://example.com/', 'status_code' => 200, 'http_version' => '1.1', 'response_time_ms' => 120],
            'redirects' => [],
            'scores' => ['overall' => 88, 'seo' => 90, 'performance' => 85, 'accessibility' => 87, 'security' => 90],
            'seo' => [
                'meta_title' => ['value' => 'Example', 'length' => 7], 'meta_description' => ['value' => 'Description', 'length' => 11],
                'canonical_url' => 'https://example.com/', 'headings' => ['h1' => ['Example'], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []],
                'images' => ['count' => 1, 'missing_alt_count' => 0, 'items' => []], 'internal_links' => ['count' => 1, 'items' => []],
                'broken_links' => ['count' => 0, 'checked_count' => 1, 'items' => []], 'sitemap' => ['exists' => true],
                'robots_txt' => ['exists' => true], 'schema' => ['item_count' => 1],
            ],
            'performance' => ['page_size_bytes' => 1000, 'request_count' => 2],
            'accessibility' => ['images_missing_alt' => 0, 'empty_link_count' => 0, 'unlabelled_form_control_count' => 0, 'html_language' => 'en'],
            'security' => ['uses_https' => true, 'ssl_valid' => true, 'has_hsts' => true, 'has_csp' => true, 'has_frame_protection' => true],
            'technology' => ['server' => 'nginx', 'powered_by' => null, 'hosting_provider' => null],
            'findings' => [['check_key' => 'security.https', 'category' => 'security', 'severity' => 'critical', 'status' => 'passed', 'title' => 'HTTPS', 'description' => 'Passed', 'evidence' => [], 'recommendation' => null]],
        ];
    }
}
