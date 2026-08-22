<?php

namespace App\Services\Hosting;

use App\Contracts\SslInspector;
use Carbon\Carbon;

class NativeSslInspector implements SslInspector
{
    public function inspect(string $host): array
    {
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client("ssl://{$host}:443", $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);
        if (! $socket) return ['valid' => false, 'hostname_match' => false, 'issuer' => null, 'expires_at' => null, 'error' => 'Certificate is not available yet.'];
        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate ? openssl_x509_parse($certificate) : false;
        if (! is_array($parsed)) return ['valid' => false, 'hostname_match' => false, 'issuer' => null, 'expires_at' => null, 'error' => 'Certificate details could not be read.'];
        $expires = isset($parsed['validTo_time_t']) ? Carbon::createFromTimestampUTC($parsed['validTo_time_t']) : null;
        return ['valid' => $expires?->isFuture() ?? false, 'hostname_match' => true, 'issuer' => $parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? null, 'expires_at' => $expires?->toIso8601String(), 'error' => null];
    }
}
