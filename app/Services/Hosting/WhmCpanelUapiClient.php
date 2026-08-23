<?php

namespace App\Services\Hosting;

use App\Contracts\CpanelUapiClient;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhmCpanelUapiClient implements CpanelUapiClient
{
    public function call(HostingServer $server, HostingAccount $account, string $module, string $function, array $parameters = []): array
    {
        $credentials = $server->credentials ?? [];
        $host = preg_replace('/:\d+$/', '', trim((string) $server->hostname));
        if (! $host || empty($credentials['username']) || empty($credentials['token'])) {
            throw new RuntimeException('WHM credentials are not configured.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'whm '.$credentials['username'].':'.$credentials['token'],
            ])->acceptJson()->connectTimeout(8)->timeout(30)->get("https://{$host}:2087/json-api/uapi_cpanel", [
                'api.version' => 1,
                'cpanel.user' => strtolower($account->username),
                'cpanel.module' => $module,
                'cpanel.function' => $function,
                ...$parameters,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('The CRM could not contact cPanel through WHM while configuring the WordPress database.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('WHM rejected the API token or it lacks permission to manage this cPanel account.');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('WHM returned an invalid response while configuring the WordPress database.');
        }

        $payload = $response->json();
        if ((string) data_get($payload, 'metadata.result') === '0') {
            throw new RuntimeException('WHM refused to run the cPanel database operation for this account.');
        }

        $result = data_get($payload, 'data.uapi');
        if (! is_array($result)) {
            $data = data_get($payload, 'data');
            $uapi = data_get($payload, 'data.uapi');
            Log::warning('WHM returned a malformed uapi_cpanel response.', [
                'top_level_keys' => array_values(array_map('strval', array_keys($payload))),
                'metadata_result' => $this->safeMetadataResult(data_get($payload, 'metadata.result')),
                'has_data' => array_key_exists('data', $payload),
                'has_uapi' => is_array($data) && array_key_exists('uapi', $data),
                'has_result' => is_array($uapi) && array_key_exists('result', $uapi),
            ]);
            throw new RuntimeException('WHM returned no cPanel database result for this account.');
        }

        if ((int) ($result['status'] ?? 0) !== 1) {
            $messages = $this->safeFailureMessages($result, $parameters);
            throw new RuntimeException($messages === []
                ? 'The cPanel UAPI database operation failed.'
                : 'The cPanel UAPI database operation failed: '.implode(' ', $messages));
        }

        return ['result' => $result];
    }

    private function safeFailureMessages(array $result, array $parameters): array
    {
        $secrets = collect($parameters)
            ->filter(fn ($value, $key) => preg_match('/password|token|secret/i', (string) $key) === 1)
            ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values();

        return collect([...((array) ($result['errors'] ?? [])), ...((array) ($result['messages'] ?? []))])
            ->filter(fn ($message) => is_scalar($message))
            ->map(function ($message) use ($secrets) {
                $message = trim(strip_tags((string) $message));
                foreach ($secrets as $secret) {
                    $message = str_replace($secret, '[REDACTED]', $message);
                }

                return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? '', 0, 500);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function safeMetadataResult(mixed $result): int|string|null
    {
        if (is_int($result)) return $result;
        if (is_string($result)) return mb_substr($result, 0, 20);

        return null;
    }
}
