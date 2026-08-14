<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;
use App\Models\Website;
use App\Models\HostingAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CpanelHostingProvider implements HostingProviderInterface
{
    private function client(HostingServer $server)
    {
        $credentials = $server->credentials ?? [];
        $token = $credentials['token'] ?? null;
        $username = $credentials['username'] ?? null;
        if (!$server->hostname || !$token || !$username) throw new RuntimeException('cPanel credentials are not configured.');
        return Http::withHeaders(['Authorization' => "cpanel {$username}:{$token}"])->acceptJson()->timeout(15)->baseUrl('https://'.$server->hostname.':2083');
    }
    public function testConnection(HostingServer $server): array
    {
        $response = $this->client($server)->get('/execute/Variables/get_user_information');
        return ['ok' => $response->successful(), 'message' => $response->successful() ? 'cPanel connected.' : 'cPanel connection failed.'];
    }
    public function accountMetrics(HostingServer $server, Website $website): array
    {
        $response = $this->client($server)->get('/execute/ResourceUsage/get_usages');
        if (!$response->successful()) throw new RuntimeException('Unable to read cPanel resource usage.');
        return ['hosting_status' => 'active', 'provider_payload' => $response->json('data')];
    }
    public function accounts(HostingServer $server): array { return []; }
    public function packages(HostingServer $server): array { return []; }
    public function createAccount(HostingServer $server, array $data): array { throw new RuntimeException('Account creation requires a WHM provider.'); }
    public function cpanelSession(HostingServer $server, HostingAccount $account): ?string { return null; }
}
