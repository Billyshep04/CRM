<?php

namespace App\Services\Hosting;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProvisioningHttpService
{
    public function inspect(string $domain): array
    {
        $checks = [
            'http_home' => "http://{$domain}/",
            'https_home' => "https://{$domain}/",
            'wordpress_admin' => "https://{$domain}/wp-admin/",
        ];
        $results = [];
        foreach ($checks as $key => $url) {
            try {
                $response = Http::timeout(15)->withOptions(['allow_redirects' => ['max' => 5, 'strict' => true]])->get($url);
                $results[$key] = ['status' => $response->status(), 'ok' => $response->status() >= 200 && $response->status() < 400, 'final_url' => $response->handlerStats()['url'] ?? $url, 'response_time_ms' => isset($response->handlerStats()['total_time']) ? (int) round($response->handlerStats()['total_time'] * 1000) : null];
            } catch (\Throwable) {
                $results[$key] = ['status' => null, 'ok' => false, 'final_url' => null, 'response_time_ms' => null];
            }
        }
        if (! collect($results)->every('ok')) throw new RuntimeException('The website files are installed, but the homepage or WordPress admin route is not responding correctly yet.');
        return ['ready' => true, 'checks' => $results];
    }

    /**
     * A lightweight, SSL-independent check that a freshly attached addon
     * domain is actually being served, not just recorded as attached.
     * cPanel can report an addon domain as successfully created while the
     * underlying vhost/document root never actually finishes deploying —
     * this catches that before the pipeline requests a certificate or
     * proceeds any further for a domain that isn't really live yet.
     */
    public function inspectAddonVhost(string $domain): array
    {
        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => ['max' => 5, 'strict' => true]])->get("http://{$domain}/");
            $bodyLength = strlen($response->body());
            $ready = $response->status() >= 200 && $response->status() < 400 && $bodyLength > 200;

            return ['ready' => $ready, 'status' => $response->status(), 'body_length' => $bodyLength];
        } catch (\Throwable) {
            return ['ready' => false, 'status' => null, 'body_length' => null];
        }
    }
}
