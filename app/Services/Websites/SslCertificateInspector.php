<?php

namespace App\Services\Websites;

use Carbon\CarbonImmutable;

class SslCertificateInspector
{
    /** @return array{status:string,expires_at:?CarbonImmutable,days_remaining:?int,error_reason:?string} */
    public function inspect(string $url): array
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $port = ($parts['scheme'] ?? 'https') === 'https' ? (int) ($parts['port'] ?? 443) : 443;

        if (! $host) return $this->unavailable('invalid_host');

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
            'disable_compression' => true,
        ]]);

        $errno = 0;
        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $error,
            (float) config('website-audits.connect_timeout_seconds', 5),
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! $socket) {
            $reason = $this->reason($error);
            if ($reason === 'certificate_invalid') return ['status' => 'invalid', 'expires_at' => null, 'days_remaining' => null, 'error_reason' => $reason];
            return $this->unavailable($reason);
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);
        if (! $certificate || ! ($parsed = openssl_x509_parse($certificate))) return $this->unavailable('certificate_unavailable');

        $expiresAt = isset($parsed['validTo_time_t']) ? CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t']) : null;
        if (! $expiresAt) return $this->unavailable('expiry_unavailable');

        $days = now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);
        if ($days < 0) return ['status' => 'expired', 'expires_at' => $expiresAt, 'days_remaining' => 0, 'error_reason' => 'certificate_expired'];

        return [
            'status' => $days <= config('website-monitoring.ssl_warning_days', 30) ? 'expiring' : 'valid',
            'expires_at' => $expiresAt,
            'days_remaining' => $days,
            'error_reason' => null,
        ];
    }

    private function unavailable(string $reason): array
    {
        return ['status' => 'unavailable', 'expires_at' => null, 'days_remaining' => null, 'error_reason' => $reason];
    }

    private function reason(string $error): string
    {
        $error = strtolower($error);
        if (str_contains($error, 'getaddrinfo') || str_contains($error, 'name or service')) return 'dns_failure';
        if (str_contains($error, 'certificate') || str_contains($error, 'peer')) return 'certificate_invalid';
        if (str_contains($error, 'timed out')) return 'timeout';
        return 'tls_handshake_failed';
    }
}
