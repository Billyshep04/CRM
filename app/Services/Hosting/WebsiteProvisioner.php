<?php

namespace App\Services\Hosting;

use App\Exceptions\ManualProvisioningActionRequired;
use App\Exceptions\ProvisioningWait;
use App\Models\HostingAccount;
use App\Models\WebsiteActivity;
use App\Models\WebsiteCredential;
use App\Models\WebsiteHealthCheck;
use App\Models\WebsiteProvisioningRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebsiteProvisioner
{
    public function __construct(
        private HostingProviderManager $providers,
        private KrystalWordpressProvisioner $wordpress,
        private ProvisioningDnsService $dns,
        private ProvisioningSslService $ssl,
        private ProvisioningHttpService $http,
    ) {}

    public function process(WebsiteProvisioningRun $run): WebsiteProvisioningRun
    {
        return Cache::lock("website-provisioning:{$run->id}", 900)->block(3, function () use ($run) {
            $run = $run->fresh(['website', 'steps', 'hostingServer', 'hostingPackage', 'wordpressProfile', 'account']);
            if ($run->mode === 'mock' && ! app()->environment(['local', 'testing'])) {
                $safe = 'A preview provisioning run cannot create real hosting. Enable live provisioning and start again.';
                $run->update(['state' => 'failed', 'failed_step' => 'validate_prerequisites', 'safe_error' => $safe, 'completed_at' => now()]);
                $run->website->update(['provisioning_status' => 'failed', 'lifecycle_state' => 'draft']);
                return $run->fresh(['website', 'account', 'steps']);
            }
            if ($run->state === 'complete') return $run;
            $run->update(['started_at' => $run->started_at ?? now(), 'attempts' => $run->attempts + 1, 'completed_at' => null, 'next_check_at' => null]);

            foreach ($run->steps as $step) {
                if (in_array($step->status, ['complete', 'manual_action'], true)) continue;
                try {
                    $this->executeStep($run, $step);
                } catch (ProvisioningWait $exception) {
                    $step->update(['status' => 'waiting', 'completed_at' => null, 'safe_message' => $exception->getMessage()]);
                    $run->update(['state' => $exception->state, 'failed_step' => null, 'safe_error' => null, 'next_check_at' => now()->addMinutes($exception->retryMinutes)]);
                    $run->website->update(['provisioning_status' => $exception->state]);
                    return $run->fresh(['website', 'account', 'steps']);
                } catch (ManualProvisioningActionRequired $exception) {
                    $step->update(['status' => 'manual_action', 'completed_at' => now(), 'safe_message' => $exception->getMessage()]);
                } catch (Throwable $exception) {
                    $safe = $exception instanceof RuntimeException ? $exception->getMessage() : 'Provisioning failed. Review the protected server logs.';
                    $step->update(['status' => 'failed', 'completed_at' => now(), 'safe_message' => $safe]);
                    $run->update(['state' => 'failed', 'failed_step' => $step->step, 'safe_error' => $safe]);
                    $run->website->update(['provisioning_status' => 'failed']);
                    return $run->fresh(['website', 'account', 'steps']);
                }
            }

            $run->update(['state' => 'complete', 'completed_at' => now(), 'failed_step' => null, 'safe_error' => null, 'next_check_at' => null, 'secrets_encrypted' => null]);
            $run->website->update([
                'provisioning_status' => $run->mode === 'live' ? 'complete' : 'preview_complete',
                'lifecycle_state' => $run->mode === 'live' ? 'active' : 'draft',
            ]);
            return $run->fresh(['website', 'account', 'steps']);
        });
    }

    private function executeStep(WebsiteProvisioningRun $run, $step): void
    {
        $step->update(['status' => 'running', 'started_at' => now(), 'completed_at' => null, 'attempts' => $step->attempts + 1]);
        $run->update(['state' => $this->state($step->step)]);
        $website = $run->website;
        $server = $run->hostingServer;
        $provider = $this->providers->forMode($server, $run->mode);
        $live = $run->mode === 'live';
        $result = [];

        if ($step->step === 'validate_prerequisites') {
            $result = $run->website_type === 'wordpress' ? $this->wordpress->validatePrerequisites($run->hostingPackage, $live) : ['wordpress' => false];
        } elseif ($step->step === 'create_cpanel_account') {
            $this->guardLive($run);
            if (! $run->hosting_account_id) {
                $secrets = $this->secrets($run);
                $username = $this->username($website->domain);
                $result = $provider->createAccount($server, ['username' => $username, 'domain' => $website->domain, 'password' => $secrets['cpanel_password'], 'package_name' => $run->hostingPackage?->external_id]);
                $account = HostingAccount::updateOrCreate(['hosting_server_id' => $server->id, 'external_id' => $result['external_id']], [...$result, 'customer_id' => $website->customer_id, 'last_synced_at' => null]);
                $run->update(['hosting_account_id' => $account->id, 'expected_ip' => $account->assigned_ip]);
                $website->update(['hosting_account_id' => $account->id, 'cpanel_username' => $account->username, 'hosting_enabled' => true]);
            }
        } elseif ($step->step === 'wait_for_cpanel') {
            $result = $provider->verifyAccount($server, $this->account($run));
            if (! ($result['ready'] ?? false)) throw new RuntimeException('WHM has not confirmed the cPanel account yet.');
            if ($live) {
                $ip = $result['assigned_ip'] ?? null;
                $this->account($run)->update(['assigned_ip' => $ip, 'status' => $result['status'] ?? 'active', 'last_synced_at' => now()]);
                $run->update(['expected_ip' => $ip]);
            }
        } elseif ($step->step === 'connect_ssh') {
            $result = $live ? $this->wordpress->testSsh($server, $this->account($run), $this->secrets($run)['cpanel_password']) : ['connected' => true, 'port' => 722, 'mock' => true];
        } elseif (in_array($step->step, ['download_wordpress', 'create_database', 'create_database_user', 'grant_database_privileges', 'create_wp_config', 'install_wordpress', 'configure_wordpress', 'verify_wordpress'], true)) {
            $result = $this->wordpressStep($run, $step->step, $live, $provider);
        } elseif ($step->step === 'check_dns') {
            $result = $this->dnsStep($run, $live);
        } elseif ($step->step === 'check_ssl') {
            $result = $this->sslStep($run, $live);
        } elseif ($step->step === 'install_agent') {
            if (! ($run->options['install_agent'] ?? true)) $result = ['skipped' => true];
            elseif (! $live) $result = $provider->installAgent($server, $this->account($run), ['domain' => $website->domain, 'agent_token' => $website->agent_token_encrypted]);
            else throw new ManualProvisioningActionRequired('Install the WebStamp Site Agent in WordPress and enter the website monitoring token.');
        } elseif ($step->step === 'enable_monitoring') {
            $website->update(['monitoring_enabled' => true]);
        } elseif ($step->step === 'run_initial_health_check') {
            $result = $run->mode === 'mock' ? ['ready' => true, 'mock' => true, 'checks' => ['https_home' => ['status' => 200, 'response_time_ms' => 1]]] : $this->http->inspect($run->domain);
            if ($live) {
                $home = data_get($result, 'checks.https_home', []);
                WebsiteHealthCheck::create(['website_id' => $website->id, 'check_type' => 'provisioning', 'uptime_status' => 'online', 'http_status' => $home['status'], 'response_time_ms' => $home['response_time_ms'] ?? null, 'overall_status' => 'healthy', 'checked_at' => now(), 'warnings' => [], 'errors' => [], 'metrics' => $this->safeMetadata($result)]);
                $website->update(['status' => 'healthy', 'last_checked_at' => now()]);
            }
        }

        $step->update(['status' => 'complete', 'completed_at' => now(), 'safe_message' => Str::headline($step->step).' complete.', 'metadata' => $this->safeMetadata($result)]);
        if ($step->step === 'verify_wordpress') {
            $secrets = $run->secrets_encrypted ?? [];
            unset($secrets['cpanel_password'], $secrets['database_password']);
            $run->update(['secrets_encrypted' => $secrets ?: null]);
        }
        WebsiteActivity::create(['website_id' => $website->id, 'created_by_user_id' => $run->initiated_by_user_id, 'type' => 'provisioning_'.$step->step, 'title' => Str::headline($step->step), 'performed_at' => now(), 'visible_to_customer' => in_array($step->step, ['create_cpanel_account', 'install_wordpress', 'check_dns', 'check_ssl'], true)]);
    }

    private function wordpressStep(WebsiteProvisioningRun $run, string $step, bool $live, $provider): array
    {
        $account = $this->account($run);
        $secrets = $this->secrets($run);
        $options = $run->options ?? [];
        $profile = $run->wordpressProfile?->configuration ?? [];
        $adminUsername = $options['admin_username'] ?? $profile['admin_username'] ?? config('hosting.wordpress_admin_username', 'webstamp_admin');
        $adminEmail = $options['admin_email'] ?? $profile['admin_email'] ?? config('hosting.wordpress_admin_email');
        $siteTitle = $options['site_title'] ?? str_replace('{site_name}', $run->website->name, $profile['title_template'] ?? '{site_name}');
        if (! $live) {
            if ($step === 'install_wordpress') {
                $result = $provider->installWordpress($run->hostingServer, $account, ['admin_username' => $adminUsername, 'admin_password' => $secrets['wordpress_password']]);
                $this->storeWordpressCredential($run, $adminUsername, $secrets['wordpress_password']);
                return $result;
            }
            if ($step === 'configure_wordpress') return $provider->configureWordpress($run->hostingServer, $account, ['profile_id' => $run->wordpress_profile_id, 'options' => $options]);
            $this->mockFail($run, $step);
            return ['mock' => true];
        }

        $password = $secrets['cpanel_password'];
        $database = $account->username.'_wp';
        $databaseUser = $account->username.'_wpuser';
        return match ($step) {
            'download_wordpress' => $this->wordpress->downloadWordpress($run->hostingServer, $account, $password),
            'create_database' => $this->wordpress->createDatabase($run->hostingServer, $account, $password, $database),
            'create_database_user' => $this->wordpress->createDatabaseUser($run->hostingServer, $account, $password, $databaseUser, $secrets['database_password']),
            'grant_database_privileges' => $this->wordpress->grantPrivileges($run->hostingServer, $account, $password, $database, $databaseUser),
            'create_wp_config' => $this->wordpress->createConfig($run->hostingServer, $account, $password, ['name' => $database, 'user' => $databaseUser, 'password' => $secrets['database_password']]),
            'install_wordpress' => tap($this->wordpress->install($run->hostingServer, $account, $password, ['url' => 'https://'.$run->domain, 'title' => $siteTitle, 'admin_username' => $adminUsername, 'admin_password' => $secrets['wordpress_password'], 'admin_email' => $adminEmail]), fn () => $this->storeWordpressCredential($run, $adminUsername, $secrets['wordpress_password'])),
            'configure_wordpress' => $this->wordpress->configure($run->hostingServer, $account, $password, $run->wordpressProfile, [...$options, 'site_url' => 'https://'.$run->domain]),
            'verify_wordpress' => $this->wordpress->verify($run->hostingServer, $account, $password, 'https://'.$run->domain),
        };
    }

    private function dnsStep(WebsiteProvisioningRun $run, bool $live): array
    {
        if (! $run->expected_ip && $live) throw new RuntimeException('The cPanel account does not yet have an assigned IP address.');
        $result = $live ? $this->dns->inspect($run->domain, $run->expected_ip) : ['ready' => true, 'provider' => 'mock', 'expected_ip' => $run->expected_ip ?? '127.0.0.1', 'root' => ['status' => 'correct'], 'www' => ['status' => 'correct']];
        $run->update(['dns_provider' => data_get($result, 'provider.key', $result['provider'] ?? null), 'dns_status' => $result]);
        if (! ($result['ready'] ?? false)) {
            $message = ($result['www_required'] ?? true)
                ? 'DNS connection pending. Point the root A record to the assigned IP and set www as a CNAME to the root domain.'
                : 'DNS connection pending. Point this subdomain A record to the assigned IP address.';
            throw new ProvisioningWait('waiting_for_dns', $message, config('hosting.dns_retry_minutes', 10));
        }
        return $result;
    }

    private function sslStep(WebsiteProvisioningRun $run, bool $live): array
    {
        $result = $live ? $this->ssl->inspect($run->domain) : ['ready' => true, 'root' => ['status' => 'active'], 'www' => ['status' => 'active']];
        $run->update(['ssl_status' => $result]);
        if (! ($result['ready'] ?? false)) throw new ProvisioningWait('waiting_for_ssl', 'SSL is pending. AutoSSL will be checked again after DNS has propagated.', config('hosting.ssl_retry_minutes', 10));
        return $result;
    }

    private function secrets(WebsiteProvisioningRun $run): array
    {
        $secrets = $run->secrets_encrypted ?? [];
        foreach (['cpanel_password', 'database_password', 'wordpress_password'] as $key) $secrets[$key] ??= Str::password(40, true, true, true, false);
        $run->update(['secrets_encrypted' => $secrets]);
        return $secrets;
    }

    private function storeWordpressCredential(WebsiteProvisioningRun $run, string $username, string $password): void
    {
        WebsiteCredential::updateOrCreate(['website_id' => $run->website_id, 'type' => 'wordpress_admin'], ['username' => $username, 'secret_encrypted' => $password, 'created_by_user_id' => $run->initiated_by_user_id, 'revealed_at' => null, 'revoked_at' => null]);
    }

    private function mockFail(WebsiteProvisioningRun $run, string $step): void { if (in_array($step, $run->hostingServer->metadata['mock_fail_steps'] ?? [], true)) throw new RuntimeException("Mock {$step} failure."); }
    private function account(WebsiteProvisioningRun $run): HostingAccount { return $run->account()->first() ?? throw new RuntimeException('The hosting account has not been created yet.'); }
    private function guardLive(WebsiteProvisioningRun $run): void { if ($run->mode === 'live' && ! config('hosting.allow_live_provisioning')) throw new RuntimeException('Live hosting provisioning is disabled.'); }
    private function username(string $domain): string { $base = preg_replace('/[^a-z0-9]/', '', strtolower(strtok($domain, '.'))); return substr($base ?: 'webstamp', 0, 9).Str::lower(Str::random(3)); }
    private function state(string $step): string { return ['validate_prerequisites' => 'validating', 'create_cpanel_account' => 'creating_hosting', 'wait_for_cpanel' => 'waiting_for_hosting', 'connect_ssh' => 'connecting_ssh', 'download_wordpress' => 'installing_wordpress', 'create_database' => 'installing_wordpress', 'create_database_user' => 'installing_wordpress', 'grant_database_privileges' => 'installing_wordpress', 'create_wp_config' => 'installing_wordpress', 'install_wordpress' => 'installing_wordpress', 'configure_wordpress' => 'configuring_wordpress', 'verify_wordpress' => 'verifying_wordpress', 'check_dns' => 'checking_dns', 'check_ssl' => 'checking_ssl', 'install_agent' => 'installing_agent', 'enable_monitoring' => 'enabling_monitoring', 'run_initial_health_check' => 'running_checks'][$step] ?? 'pending'; }
    private function safeMetadata(array $result): array { return collect($result)->except(['password', 'admin_password', 'database_password', 'token', 'agent_token', 'api_token', 'output'])->all(); }
}
