<?php

namespace App\Services\WebsiteAnalysis;

use App\Contracts\WebsiteAnalyzer;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

class DeterministicWebsiteAnalyzer implements WebsiteAnalyzer
{
    public function __construct(private readonly HttpWebsiteClient $client) {}

    public function analyze(string $url): array
    {
        $fetch = $this->client->fetch($url);
        $response = $fetch['response'];
        $html = $response->body();
        $finalUrl = $fetch['final_url'];
        [$document, $xpath] = $this->document($html);

        $title = $this->firstText($xpath, '//title');
        $description = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]', 'content');
        $canonical = $this->attribute($xpath, '//link[contains(concat(" ", translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]', 'href');
        $canonical = $canonical ? $this->client->resolveUrl($finalUrl, $canonical) : null;
        $headings = $this->headings($xpath);
        $images = $this->images($xpath, $finalUrl);
        $links = $this->links($xpath, $finalUrl);
        $internalLinks = array_values(array_filter($links, fn (array $link): bool => $link['internal']));
        $brokenLinks = $this->brokenLinks($internalLinks);
        $assets = $this->assets($xpath, $finalUrl);
        $robotsUrl = $this->origin($finalUrl).'/robots.txt';
        $sitemapUrl = $this->origin($finalUrl).'/sitemap.xml';
        $robotsStatus = $this->client->probe($robotsUrl);
        $sitemapStatus = $this->client->probe($sitemapUrl);
        $notFoundUrl = $this->origin($finalUrl).'/leadforge-404-check-'.bin2hex(random_bytes(6));
        $notFoundStatus = $this->client->probe($notFoundUrl);
        $headers = array_change_key_case($response->headers(), CASE_LOWER);
        $schema = $this->schema($xpath);
        $analytics = $this->analytics($html);
        $technology = $this->technology($html, $headers);
        $pages = $this->specialPages($internalLinks);
        $accessibility = $this->accessibility($xpath, $images);
        $mobile = $this->mobile($xpath, $html);
        $security = $this->security($finalUrl, $headers);

        $seoScore = $this->seoScore($title, $description, $headings, $images, $brokenLinks, $canonical, $sitemapStatus, $robotsStatus);
        $performanceScore = $this->performanceScore(strlen($html), count($assets), $fetch['duration_ms']);
        $accessibilityScore = $this->accessibilityScore($accessibility);
        $securityScore = $this->securityScore($security);
        $findings = $this->findings($title, $description, $headings, $images, $brokenLinks, $canonical, $sitemapStatus, $robotsStatus, $pages, $analytics, $schema, $notFoundStatus, $security, strlen($html), count($assets), $accessibility);

        return [
            'homepage' => [
                'requested_url' => $url,
                'final_url' => $finalUrl,
                'status_code' => $response->status(),
                'http_version' => $response->toPsrResponse()->getProtocolVersion(),
                'content_type' => $response->header('Content-Type'),
                'response_time_ms' => $fetch['duration_ms'],
            ],
            'redirects' => $fetch['redirects'],
            'seo' => [
                'score' => $seoScore,
                'meta_title' => ['value' => $title, 'length' => mb_strlen((string) $title)],
                'meta_description' => ['value' => $description, 'length' => mb_strlen((string) $description)],
                'headings' => $headings,
                'canonical_url' => $canonical,
                'images' => ['count' => count($images), 'missing_alt_count' => count(array_filter($images, fn (array $image): bool => ! $image['has_alt'])), 'items' => array_slice($images, 0, 100)],
                'internal_links' => ['count' => count($internalLinks), 'items' => array_slice($internalLinks, 0, 100)],
                'broken_links' => ['checked_count' => min(count($internalLinks), (int) config('website-audits.max_links_to_check')), 'count' => count($brokenLinks), 'items' => $brokenLinks],
                'robots_txt' => ['url' => $robotsUrl, 'exists' => $this->isSuccessfulStatus($robotsStatus), 'status_code' => $robotsStatus],
                'sitemap' => ['url' => $sitemapUrl, 'exists' => $this->isSuccessfulStatus($sitemapStatus), 'status_code' => $sitemapStatus],
                'schema' => $schema,
            ],
            'performance' => [
                'score' => $performanceScore,
                'page_size_bytes' => strlen($html),
                'request_count' => count($assets),
                'request_count_method' => 'unique_homepage_resources_discovered',
                'assets' => array_slice($assets, 0, 150),
            ],
            'accessibility' => ['score' => $accessibilityScore] + $accessibility,
            'mobile' => $mobile,
            'security' => ['score' => $securityScore] + $security,
            'technology' => $technology,
            'marketing' => $analytics,
            'pages' => $pages + ['custom_404' => ['tested_url' => $notFoundUrl, 'status_code' => $notFoundStatus, 'valid_status' => $notFoundStatus === 404]],
            'social_links' => $this->socialLinks($links),
            'scores' => [
                'overall' => round(($seoScore + $performanceScore + $accessibilityScore + $securityScore) / 4, 2),
                'seo' => $seoScore,
                'performance' => $performanceScore,
                'accessibility' => $accessibilityScore,
                'security' => $securityScore,
            ],
            'findings' => $findings,
            'limitations' => [
                'request_count' => 'Counts unique resources referenced by homepage markup; it is not a browser network trace.',
                'accessibility' => 'Automated checks do not establish complete WCAG compliance.',
                'security' => 'Passive checks only; this is not penetration testing.',
            ],
        ];
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function document(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$document, new DOMXPath($document)];
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);
        $value = $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '') : '';

        return $value !== '' ? $value : null;
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)?->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }
        $value = trim($node->getAttribute($attribute));

        return $value !== '' ? $value : null;
    }

    /** @return array<string, list<string>> */
    private function headings(DOMXPath $xpath): array
    {
        $result = [];
        foreach (range(1, 6) as $level) {
            $result['h'.$level] = $this->texts($xpath->query('//h'.$level));
        }

        return $result;
    }

    /** @return list<string> */
    private function texts(DOMNodeList|false|null $nodes): array
    {
        $values = [];
        if (! $nodes) {
            return $values;
        }
        foreach ($nodes as $node) {
            $value = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($value !== '') {
                $values[] = mb_substr($value, 0, 500);
            }
        }

        return $values;
    }

    /** @return list<array{src: string, alt: string, has_alt: bool}> */
    private function images(DOMXPath $xpath, string $base): array
    {
        $images = [];
        foreach ($xpath->query('//img[@src]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $images[] = ['src' => $this->client->resolveUrl($base, $node->getAttribute('src')), 'alt' => trim($node->getAttribute('alt')), 'has_alt' => $node->hasAttribute('alt') && trim($node->getAttribute('alt')) !== ''];
        }

        return $images;
    }

    /** @return list<array{url: string, text: string, internal: bool}> */
    private function links(DOMXPath $xpath, string $base): array
    {
        $links = [];
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $href = trim($node->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
                continue;
            }
            $resolved = $this->client->resolveUrl($base, $href);
            $links[$resolved] = ['url' => $resolved, 'text' => mb_substr(trim($node->textContent), 0, 255), 'internal' => strtolower((string) parse_url($resolved, PHP_URL_HOST)) === $host];
        }

        return array_values($links);
    }

    /** @param list<array{url: string, text: string, internal: bool}> $links */
    private function brokenLinks(array $links): array
    {
        $broken = [];
        foreach (array_slice($links, 0, (int) config('website-audits.max_links_to_check', 50)) as $link) {
            $status = $this->client->probe($link['url']);
            if ($status === null || $status >= 400) {
                $broken[] = $link + ['status_code' => $status];
            }
        }

        return $broken;
    }

    /** @return list<array{type: string, url: string}> */
    private function assets(DOMXPath $xpath, string $base): array
    {
        $queries = ['script' => '//script[@src]', 'image' => '//img[@src]', 'stylesheet' => '//link[contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"stylesheet")][@href]', 'iframe' => '//iframe[@src]'];
        $assets = [];
        foreach ($queries as $type => $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $attribute = $type === 'stylesheet' ? 'href' : 'src';
                $assetUrl = $this->client->resolveUrl($base, $node->getAttribute($attribute));
                $assets[$assetUrl] = ['type' => $type, 'url' => $assetUrl];
            }
        }

        return array_values($assets);
    }

    private function schema(DOMXPath $xpath): array
    {
        $types = [];
        $count = 0;
        foreach ($xpath->query('//script[contains(translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"ld+json")]') ?: [] as $node) {
            $decoded = json_decode($node->textContent, true);
            if (! is_array($decoded)) {
                continue;
            }
            $items = isset($decoded['@graph']) && is_array($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $count++;
                foreach ((array) ($item['@type'] ?? []) as $type) {
                    if (is_string($type)) {
                        $types[] = $type;
                    }
                }
            }
        }

        return ['present' => $count > 0, 'item_count' => $count, 'types' => array_values(array_unique($types))];
    }

    private function analytics(string $html): array
    {
        preg_match_all('/\b(?:G-[A-Z0-9]+|UA-\d+-\d+|GTM-[A-Z0-9]+)\b/i', $html, $matches);

        return [
            'analytics' => ['detected' => $matches[0] !== [] || str_contains($html, 'googletagmanager.com'), 'identifiers' => array_values(array_unique($matches[0]))],
            'facebook_pixel' => ['detected' => str_contains($html, 'connect.facebook.net') || preg_match('/\bfbq\s*\(/i', $html) === 1],
            'cookie_banner' => ['detected' => preg_match('/(?:cookiebot|onetrust|cookieyes|cookie[-_ ]consent|accept (?:all )?cookies)/i', $html) === 1],
        ];
    }

    private function technology(string $html, array $headers): array
    {
        $generator = null;
        if (preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $html, $match)) {
            $generator = $match[1];
        }
        $cms = match (true) {
            str_contains($html, 'wp-content/') || str_contains(strtolower((string) $generator), 'wordpress') => 'WordPress',
            str_contains($html, 'cdn.shopify.com') || str_contains(strtolower((string) $generator), 'shopify') => 'Shopify',
            str_contains($html, 'wixstatic.com') => 'Wix',
            str_contains($html, 'static1.squarespace.com') => 'Squarespace',
            str_contains(strtolower((string) $generator), 'joomla') => 'Joomla',
            str_contains(strtolower((string) $generator), 'drupal') => 'Drupal',
            default => $generator,
        };
        $provider = match (true) {
            isset($headers['cf-ray']) => 'Cloudflare',
            isset($headers['x-vercel-id']) => 'Vercel',
            isset($headers['x-amz-cf-id']) => 'Amazon CloudFront',
            isset($headers['x-served-by']) && str_contains(strtolower(implode(' ', $headers['x-served-by'])), 'cache-') => 'Fastly',
            default => null,
        };

        return ['cms' => $cms, 'server' => $headers['server'][0] ?? null, 'powered_by' => $headers['x-powered-by'][0] ?? null, 'hosting_provider' => $provider, 'hosting_detection' => $provider ? 'response_header_signal' : 'not_determined'];
    }

    private function accessibility(DOMXPath $xpath, array $images): array
    {
        $emptyLinks = 0;
        foreach ($xpath->query('//a') ?: [] as $node) {
            if (trim($node->textContent) === '' && ! ($node instanceof DOMElement && $node->hasAttribute('aria-label'))) {
                $emptyLinks++;
            }
        }
        $unlabelled = $xpath->query('//input[not(@type="hidden") and not(@aria-label) and not(@aria-labelledby) and not(@id)] | //select[not(@aria-label) and not(@aria-labelledby) and not(@id)] | //textarea[not(@aria-label) and not(@aria-labelledby) and not(@id)]')?->length ?? 0;

        return ['html_language' => $this->attribute($xpath, '//html', 'lang'), 'images_missing_alt' => count(array_filter($images, fn (array $image): bool => ! $image['has_alt'])), 'empty_link_count' => $emptyLinks, 'unlabelled_form_control_count' => $unlabelled];
    }

    private function mobile(DOMXPath $xpath, string $html): array
    {
        $viewport = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]', 'content');
        $responsiveCss = preg_match('/@media\s*\([^)]*(?:max|min)-width/i', $html) === 1;
        $score = ($viewport ? 70 : 0) + ($responsiveCss ? 30 : 0);

        return [
            'score' => $score,
            'viewport_present' => $viewport !== null,
            'viewport_content' => $viewport,
            'responsive_css_detected' => $responsiveCss,
            'method' => 'homepage_markup_signals',
        ];
    }

    private function security(string $url, array $headers): array
    {
        $https = parse_url($url, PHP_URL_SCHEME) === 'https';

        return ['uses_https' => $https, 'ssl_valid' => $https, 'has_hsts' => isset($headers['strict-transport-security']), 'has_csp' => isset($headers['content-security-policy']), 'has_frame_protection' => isset($headers['x-frame-options']) || str_contains(strtolower($headers['content-security-policy'][0] ?? ''), 'frame-ancestors')];
    }

    private function specialPages(array $links): array
    {
        $find = fn (string $pattern): ?string => collect($links)->first(fn (array $link): bool => preg_match($pattern, strtolower($link['url'].' '.$link['text'])) === 1)['url'] ?? null;
        $contact = $find('/\b(contact|contact-us|get-in-touch)\b/');
        $privacy = $find('/\b(privacy|privacy-policy|data-protection)\b/');

        return ['contact_page' => ['found' => $contact !== null, 'url' => $contact], 'privacy_policy' => ['found' => $privacy !== null, 'url' => $privacy]];
    }

    private function socialLinks(array $links): array
    {
        $domains = ['facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'tiktok.com'];

        return array_values(array_filter($links, function (array $link) use ($domains): bool {
            $host = strtolower((string) parse_url($link['url'], PHP_URL_HOST));

            return collect($domains)->contains(fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain));
        }));
    }

    private function seoScore(?string $title, ?string $description, array $headings, array $images, array $broken, ?string $canonical, ?int $sitemap, ?int $robots): float
    {
        $score = 100;
        if (! $title) {
            $score -= 20;
        } elseif (mb_strlen($title) < 20 || mb_strlen($title) > 65) {
            $score -= 5;
        }
        if (! $description) {
            $score -= 15;
        } elseif (mb_strlen($description) < 70 || mb_strlen($description) > 170) {
            $score -= 5;
        }
        if (count($headings['h1']) !== 1) {
            $score -= 10;
        }
        $score -= min(15, count(array_filter($images, fn (array $image): bool => ! $image['has_alt'])) * 2);
        $score -= min(20, count($broken) * 4);
        if (! $canonical) {
            $score -= 5;
        }
        if (! $this->isSuccessfulStatus($sitemap)) {
            $score -= 5;
        }
        if (! $this->isSuccessfulStatus($robots)) {
            $score -= 5;
        }

        return max(0, $score);
    }

    private function performanceScore(int $bytes, int $requests, int $duration): float
    {
        return max(0, 100 - max(0, ($bytes - 500_000) / 100_000 * 2) - max(0, $requests - 30) * .75 - max(0, ($duration - 1000) / 250 * 2));
    }

    private function accessibilityScore(array $data): float
    {
        return max(0, 100 - $data['images_missing_alt'] * 3 - $data['empty_link_count'] * 4 - $data['unlabelled_form_control_count'] * 5 - ($data['html_language'] ? 0 : 10));
    }

    private function securityScore(array $data): float
    {
        return max(0, 100 - ($data['uses_https'] ? 0 : 45) - ($data['ssl_valid'] ? 0 : 25) - ($data['has_hsts'] ? 0 : 10) - ($data['has_csp'] ? 0 : 10) - ($data['has_frame_protection'] ? 0 : 10));
    }

    private function findings(?string $title, ?string $description, array $headings, array $images, array $broken, ?string $canonical, ?int $sitemap, ?int $robots, array $pages, array $analytics, array $schema, ?int $notFound, array $security, int $bytes, int $requests, array $accessibility): array
    {
        $definitions = [
            ['seo.meta_title', 'seo', $title !== null, 'high', 'Homepage meta title', $title],
            ['seo.meta_description', 'seo', $description !== null, 'medium', 'Homepage meta description', $description],
            ['seo.single_h1', 'seo', count($headings['h1']) === 1, 'high', 'Exactly one H1 heading', ['count' => count($headings['h1'])]],
            ['seo.image_alt', 'seo', $accessibility['images_missing_alt'] === 0, 'medium', 'Image alternative text', ['missing' => $accessibility['images_missing_alt'], 'total' => count($images)]],
            ['seo.broken_links', 'seo', count($broken) === 0, 'high', 'Internal broken links', ['broken' => count($broken)]],
            ['seo.canonical', 'seo', $canonical !== null, 'medium', 'Canonical tag', $canonical],
            ['seo.sitemap', 'seo', $this->isSuccessfulStatus($sitemap), 'medium', 'XML sitemap', ['status' => $sitemap]],
            ['seo.robots', 'seo', $this->isSuccessfulStatus($robots), 'low', 'Robots.txt', ['status' => $robots]],
            ['marketing.analytics', 'marketing', $analytics['analytics']['detected'], 'info', 'Analytics detected', $analytics['analytics']],
            ['marketing.facebook_pixel', 'marketing', $analytics['facebook_pixel']['detected'], 'info', 'Facebook Pixel detected', $analytics['facebook_pixel']],
            ['seo.schema', 'seo', $schema['present'], 'medium', 'Structured data', $schema],
            ['business.contact_page', 'content', $pages['contact_page']['found'], 'high', 'Contact page', $pages['contact_page']],
            ['legal.privacy_policy', 'content', $pages['privacy_policy']['found'], 'high', 'Privacy policy', $pages['privacy_policy']],
            ['legal.cookie_banner', 'content', $analytics['cookie_banner']['detected'], 'medium', 'Cookie banner', $analytics['cookie_banner']],
            ['technical.404', 'technical', $notFound === 404, 'medium', 'Correct 404 response', ['status' => $notFound]],
            ['performance.page_size', 'performance', $bytes <= 2_000_000, 'medium', 'Homepage HTML size', ['bytes' => $bytes]],
            ['performance.requests', 'performance', $requests <= 80, 'medium', 'Homepage resource count', ['count' => $requests]],
            ['security.https', 'security', $security['uses_https'] && $security['ssl_valid'], 'critical', 'HTTPS and valid SSL', $security],
            ['accessibility.language', 'accessibility', $accessibility['html_language'] !== null, 'medium', 'Document language', ['language' => $accessibility['html_language']]],
            ['accessibility.form_labels', 'accessibility', $accessibility['unlabelled_form_control_count'] === 0, 'high', 'Form control labels', ['unlabelled' => $accessibility['unlabelled_form_control_count']]],
        ];

        return array_map(fn (array $item): array => ['check_key' => $item[0], 'category' => $item[1], 'status' => $item[2] ? 'passed' : 'failed', 'severity' => $item[3], 'title' => $item[4], 'description' => $item[2] ? 'The check passed.' : 'The check requires attention.', 'evidence' => ['value' => $item[5]], 'recommendation' => $item[2] ? null : 'Review and correct this issue on the website.'], $definitions);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function isSuccessfulStatus(?int $status): bool
    {
        return $status !== null && $status >= 200 && $status < 400;
    }
}
