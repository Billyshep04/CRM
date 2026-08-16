<?php

namespace App\Http\Controllers;

use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Website;
use App\Services\Hosting\HostingAccountSyncService;
use App\Services\Hosting\HostingProviderManager;
use Illuminate\Http\Request;
use RuntimeException;

class HostingAccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => HostingAccount::with([
            'server:id,name,provider,status',
            'customer:id,name',
            'websites:id,name,domain,hosting_account_id',
        ])->when(
            $request->filled('hosting_server_id'),
            fn ($query) => $query->where('hosting_server_id', $request->integer('hosting_server_id'))
        )->when(
            $request->boolean('unassigned'),
            fn ($query) => $query->whereNull('customer_id')
        )->orderBy('username')->get()]);
    }

    public function sync(HostingServer $hostingServer, HostingAccountSyncService $sync)
    {
        try {
            return response()->json(['data' => $sync->sync($hostingServer)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function update(Request $request, HostingAccount $hostingAccount)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'website_id' => ['nullable', 'exists:websites,id'],
        ]);

        $hostingAccount->update(['customer_id' => $data['customer_id'] ?? null]);

        if (! empty($data['website_id'])) {
            $website = Website::findOrFail($data['website_id']);
            $metadata = $website->metadata ?? [];
            unset($metadata['hosting_assignment_excluded']);
            $website->update([
                'hosting_server_id' => $hostingAccount->hosting_server_id,
                'hosting_account_id' => $hostingAccount->id,
                'cpanel_username' => $hostingAccount->username,
                'hosting_enabled' => true,
                'metadata' => $metadata,
            ]);
        }

        return response()->json(['data' => $hostingAccount->fresh(['customer', 'websites'])]);
    }

    public function session(HostingAccount $hostingAccount, HostingProviderManager $providers)
    {
        $url = $providers->for($hostingAccount->server)->cpanelSession($hostingAccount->server, $hostingAccount);
        if (! $url) {
            return response()->json(['message' => 'Temporary cPanel sessions are not available for this provider.'], 422);
        }

        return response()->json(['data' => ['url' => $url]]);
    }
}
