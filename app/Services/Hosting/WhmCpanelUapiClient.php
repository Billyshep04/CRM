<?php

namespace App\Services\Hosting;

use App\Contracts\CpanelUapiClient;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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
            ])->acceptJson()->connectTimeout(8)->timeout(30)->get("https://{$host}:2087/json-api/cpanel", [
                'api.version' => 1,
                'cpanel_jsonapi_user' => strtolower($account->username),
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_module' => $module,
                'cpanel_jsonapi_func' => $function,
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

        $result = data_get($payload, 'data.result') ?? data_get($payload, 'data.uapi.result');
        if (! is_array($result)) {
            throw new RuntimeException('WHM returned no cPanel database result for this account.');
        }

        return ['result' => $result];
    }
}
