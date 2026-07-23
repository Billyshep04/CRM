<?php

namespace App\Services\WebsiteAnalysis;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpWebsiteClient
{
    public function __construct(private readonly SafeUrlGuard $guard) {}

    /** @return array{response: Response, final_url: string, redirects: list<array<string, mixed>>, duration_ms: int} */
    public function fetch(string $url): array
    {
        $redirects = [];
        $current = $this->normalizeUrl($url);
        $maximum = (int) config('website-audits.max_redirects', 8);
        $started = hrtime(true);

        for ($attempt = 0; $attempt <= $maximum; $attempt++) {
            $this->guard->assertSafe($current);
            $response = $this->request('GET', $current);

            if (! $response->redirect()) {
                $bodyLength = strlen($response->body());
                if ($bodyLength > (int) config('website-audits.max_body_bytes', 5_000_000)) {
                    throw new RuntimeException('The homepage exceeds the configured audit size limit.');
                }

                return [
                    'response' => $response,
                    'final_url' => $current,
                    'redirects' => $redirects,
                    'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                ];
            }

            $location = $response->header('Location');
            if (! $location) {
                throw new RuntimeException('The website returned a redirect without a Location header.');
            }

            $next = $this->resolveUrl($current, $location);
            $this->guard->assertSafe($next);
            $redirects[] = ['from' => $current, 'to' => $next, 'status' => $response->status()];
            $current = $next;
        }

        throw new RuntimeException('The website exceeded the maximum redirect count.');
    }

    public function probe(string $url): ?int
    {
        try {
            $this->guard->assertSafe($url);
            $response = $this->request('HEAD', $url);

            if (in_array($response->status(), [405, 501], true)) {
                $response = $this->request('GET', $url);
            }

            return $response->status();
        } catch (\Throwable) {
            return null;
        }
    }

    private function request(string $method, string $url): Response
    {
        return Http::withHeaders(['User-Agent' => config('website-audits.user_agent')])
            ->timeout((int) config('website-audits.timeout_seconds', 15))
            ->connectTimeout((int) config('website-audits.connect_timeout_seconds', 5))
            ->withOptions(['allow_redirects' => false, 'verify' => true])
            ->send($method, $url);
    }

    public function resolveUrl(string $base, string $reference): string
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5));
        if (preg_match('#^https?://#i', $reference)) {
            return $this->normalizeUrl($reference);
        }

        $baseParts = parse_url($base);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        if (str_starts_with($reference, '//')) {
            return $this->normalizeUrl($scheme.':'.$reference);
        }

        if (str_starts_with($reference, '/')) {
            return $this->normalizeUrl($scheme.'://'.$host.$port.$reference);
        }

        $path = $baseParts['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : dirname($path).'/';

        return $this->normalizeUrl($scheme.'://'.$host.$port.$directory.$reference);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            throw new RuntimeException('The website URL is invalid.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }
}
