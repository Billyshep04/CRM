<?php

namespace App\Services\Hosting;

use App\Exceptions\ProvisioningWait;
use App\Models\WebsiteActivity;
use App\Models\WebsiteLaunchRun;
use App\Services\Websites\WebsiteMonitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebsiteLaunchService
{
    public function __construct(
        private HostingProviderManager $providers,
        private KrystalWordpressProvisioner $wordpress,
        private WhmCpanelUapiClient $uapi,
        private ProvisioningDnsService $dns,
        private ProvisioningSslService $ssl,
        private ProvisioningHttpService $http,
        private WebsiteMonitor $monitor,
    ) {}

    public function process(WebsiteLaunchRun $run): WebsiteLaunchRun
    {
        return Cache::lock("website-launch:{$run->id}", 900)->block(3, function () use ($run) {
            $run = $run->fresh(['website.hostingServer', 'account', 'steps']);
            if (! $run->website || ! $run->account) return $this->fail($run, null, 'The website or hosting account is no longer available.');
            if ($run->state === 'complete') return $run;
            $run->update(['started_at' => $run->started_at ?? now(), 'attempts' => $run->attempts + 1, 'completed_at' => null, 'next_check_at' => null]);

            foreach ($run->steps as $step) {
                if ($step->status === 'complete') continue;
                try {
                    $this->executeStep($run, $step);
                } catch (ProvisioningWait $exception) {
                    $step->update(['status' => 'waiting', 'safe_message' => $exception->getMessage(), 'completed_at' => null]);
                    $run->update(['state' => $exception->state, 'failed_step' => null, 'safe_error' => null, 'next_check_at' => now()->addMinutes($exception->retryMinutes)]);
                    return $run->fresh(['website', 'account', 'steps']);
                } catch (Throwable $exception) {
                    return $this->fail($run, $step, $exception instanceof RuntimeException ? $exception->getMessage() : 'Launch failed. Review the protected server logs.');
                }
            }

            $website = $run->website;
            $website->update([
                'domain' => $run->production_domain, 'current_domain' => $run->production_domain,
                'production_domain' => $run->production_domain, 'environment' => 'production',
                'login_url' => 'https://'.$run->production_domain.'/wp-admin/', 'went_live_at' => now(),
            ]);
            $run->update(['state' => 'complete', 'failed_step' => null, 'safe_error' => null, 'completed_at' => now(), 'next_check_at' => null, 'recovery_encrypted' => null]);
            WebsiteActivity::create(['website_id' => $website->id, 'created_by_user_id' => $run->initiated_by_user_id, 'type' => 'website_went_live', 'title' => 'Website went live', 'description' => "Launched {$run->development_domain} as {$run->production_domain}.", 'performed_at' => now(), 'visible_to_customer' => true]);
            return $run->fresh(['website', 'account', 'steps']);
        });
    }

    private function executeStep(WebsiteLaunchRun $run, $step): void
    {
        $step->update(['status' => 'running', 'started_at' => now(), 'completed_at' => null, 'attempts' => $step->attempts + 1]);
        $run->update(['state' => $this->state($step->step)]);
        $website = $run->website;
        $account = $run->account;
        $server = $website->hostingServer;
        $provider = $this->providers->for($server);
        if (! $provider instanceof KrystalWhmProvider) throw new RuntimeException('Go Live currently requires a Krystal WHM connection.');
        $password = (string) $account->automation_password_encrypted;
        $result = [];

        if ($step->step === 'preflight') {
            if ($website->environment !== 'development' || ! $website->development_domain) throw new RuntimeException('Only a development website can use Go Live.');
            if ($account->provider_missing || $account->status !== 'active') throw new RuntimeException('The linked Krystal hosting account is missing or inactive. Sync hosting before retrying.');
            if ($password === '') throw new RuntimeException('This development account does not have retained automation credentials. Reconnect the account before launching.');
            $matches = $provider->domainOwners($server, $run->production_domain);
            if ($matches !== []) throw new RuntimeException('The production domain already exists on Krystal. Remove the conflict or explicitly link that account before launching.');
            $verified = $provider->verifyAccount($server, $account);
            if (! ($verified['ready'] ?? false)) throw new RuntimeException('Krystal could not verify the development hosting account.');
            $this->wordpress->testSsh($server, $account, $password);
            $this->wordpress->verify($server, $account, $password, 'https://'.$run->development_domain);
            $run->update(['expected_ip' => $verified['assigned_ip'] ?? $account->assigned_ip, 'recovery_encrypted' => ['original_domain' => $run->development_domain, 'original_username' => $account->username]]);
            $result = ['ready' => true, 'account' => $account->username, 'expected_ip' => $verified['assigned_ip'] ?? $account->assigned_ip];
        } elseif ($step->step === 'check_dns') {
            if (! $run->expected_ip) throw new RuntimeException('The hosting account has no assigned IP address.');
            $result = $this->dns->inspect($run->production_domain, $run->expected_ip);
            $run->update(['dns_status' => $result]);
            if (! ($result['ready'] ?? false) && ! ($run->options['dns_override'] ?? false)) throw new ProvisioningWait('waiting_for_dns', 'DNS is not pointing to this Krystal account yet. Update the displayed records, then choose Check again.', config('hosting.dns_retry_minutes', 10));
        } elseif ($step->step === 'change_primary_domain') {
            $result = $provider->changePrimaryDomain($server, $account, $run->production_domain);
            $account->update(['external_id' => $result['external_id'], 'username' => $result['username'], 'primary_domain' => $run->production_domain, 'domains' => [['domain' => $run->production_domain, 'type' => 'primary']], 'assigned_ip' => $result['assigned_ip'] ?? $account->assigned_ip, 'last_synced_at' => now(), 'provider_missing' => false, 'provider_missing_at' => null]);
            $website->update(['cpanel_username' => $result['username']]);
        } elseif ($step->step === 'reconcile_account') {
            $result = $provider->verifyAccount($server, $account->fresh());
            if (! ($result['ready'] ?? false) || strtolower($result['username']) !== strtolower($account->username)) throw new RuntimeException('Krystal did not confirm the renamed hosting account.');
        } elseif ($step->step === 'migrate_wordpress') {
            $result = $this->wordpress->migrateDomain($server, $account, $password, 'https://'.$run->development_domain, 'https://'.$run->production_domain, (bool) ($run->options['enable_indexing'] ?? false));
        } elseif ($step->step === 'trigger_autossl') {
            $this->uapi->call($server, $account, 'SSL', 'start_autossl_check');
            $result = ['requested' => true];
        } elseif ($step->step === 'check_ssl') {
            $result = $this->ssl->inspect($run->production_domain);
            $run->update(['ssl_status' => $result]);
            if (! ($result['ready'] ?? false)) throw new ProvisioningWait('waiting_for_ssl', 'DNS is connected and AutoSSL is still being issued. Check again shortly.', config('hosting.ssl_retry_minutes', 10));
        } elseif ($step->step === 'verify_production') {
            $this->wordpress->verify($server, $account, $password, 'https://'.$run->production_domain);
            $result = $this->http->inspect($run->production_domain);
            $website->update(['domain' => $run->production_domain, 'current_domain' => $run->production_domain, 'login_url' => 'https://'.$run->production_domain.'/wp-admin/']);
            if ($website->monitoring_enabled) {
                $check = $this->monitor->check($website->fresh(['hostingServer', 'hostingAccount']), 'manual');
                if ($website->agent_token_encrypted && ! $check->wordpress_checked_at) throw new RuntimeException('The website is live, but the monitoring plugin has not reconnected on the production domain yet.');
            }
        }

        $step->update(['status' => 'complete', 'completed_at' => now(), 'safe_message' => Str::headline($step->step).' complete.', 'metadata' => $this->safeMetadata($result)]);
    }

    private function fail(WebsiteLaunchRun $run, $step, string $message): WebsiteLaunchRun
    {
        $step?->update(['status' => 'failed', 'completed_at' => now(), 'safe_message' => $message]);
        $run->update(['state' => 'failed', 'failed_step' => $step?->step ?? 'preflight', 'safe_error' => $message, 'next_check_at' => null]);
        return $run->fresh(['website', 'account', 'steps']);
    }

    private function state(string $step): string
    {
        return ['preflight' => 'validating', 'check_dns' => 'checking_dns', 'change_primary_domain' => 'changing_domain', 'reconcile_account' => 'reconciling_hosting', 'migrate_wordpress' => 'migrating_wordpress', 'trigger_autossl' => 'requesting_ssl', 'check_ssl' => 'checking_ssl', 'verify_production' => 'verifying_production'][$step] ?? 'pending';
    }

    private function safeMetadata(array $result): array
    {
        return collect($result)->except(['password', 'token', 'stdout', 'stderr', 'output'])->all();
    }
}
