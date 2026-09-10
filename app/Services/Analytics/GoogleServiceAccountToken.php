<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use phpseclib3\Crypt\RSA;

/**
 * Mints and caches a Google OAuth2 access token for a service account by
 * signing a JWT assertion with phpseclib (RS256) and exchanging it at the
 * token endpoint. No Google SDK required.
 */
class GoogleServiceAccountToken
{
    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    public function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'analytics:google:token:'.md5($credentials['client_email'].'|'.$this->scope());

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->mint($credentials);
        Cache::put($cacheKey, $token['access_token'], max(60, $token['expires_in'] - 60));

        return $token['access_token'];
    }

    public function clientEmail(): string
    {
        return $this->credentials()['client_email'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{access_token: string, expires_in: int}
     */
    private function mint(array $credentials): array
    {
        $now = time();
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => $this->scope(),
            'aud' => $credentials['token_uri'] ?? config('analytics.google.token_endpoint'),
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];

        $key = RSA::loadPrivateKey($credentials['private_key'])
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PKCS1);
        $segments[] = $this->base64Url($key->sign(implode('.', $segments)));

        $response = Http::asForm()
            ->timeout((int) config('analytics.google.timeout', 30))
            ->retry(2, 500)
            ->post((string) ($credentials['token_uri'] ?? config('analytics.google.token_endpoint')), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('Google rejected the analytics service-account assertion: '.$response->body());
        }

        return [
            'access_token' => (string) $response->json('access_token'),
            'expires_in' => (int) ($response->json('expires_in') ?? 3600),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $raw = (string) config('analytics.google.credentials_json');
        if ($raw === '' && ($path = (string) config('analytics.google.credentials_path')) !== '') {
            if (! is_readable($path)) {
                throw new RuntimeException("Analytics service-account file is not readable at {$path}.");
            }
            $raw = (string) file_get_contents($path);
        }

        if ($raw === '') {
            throw new RuntimeException('Google Analytics credentials are not configured. Set GOOGLE_ANALYTICS_CREDENTIALS_PATH or GOOGLE_ANALYTICS_CREDENTIALS_JSON.');
        }

        // Allow the JSON to be provided base64-encoded (convenient in .env).
        if (! str_starts_with(ltrim($raw), '{')) {
            $decoded = base64_decode(trim($raw), true);
            if ($decoded !== false) {
                $raw = $decoded;
            }
        }

        $parsed = json_decode($raw, true);
        if (! is_array($parsed) || empty($parsed['client_email']) || empty($parsed['private_key'])) {
            throw new RuntimeException('Google Analytics credentials JSON is missing client_email or private_key.');
        }

        return $this->credentials = $parsed;
    }

    private function scope(): string
    {
        return (string) config('analytics.google.scope', 'https://www.googleapis.com/auth/analytics.readonly');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
