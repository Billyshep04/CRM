<?php

namespace App\Providers;

use App\Contracts\LeadDiscoveryProvider;
use App\Contracts\LeadScoreRepository;
use App\Contracts\LeadScoringEngine;
use App\Contracts\DnsResolver;
use App\Contracts\SshCommandRunner;
use App\Contracts\CpanelUapiClient;
use App\Contracts\SslInspector;
use App\Contracts\WebsiteAnalyzer;
use App\Contracts\WebsiteAuditRepository;
use App\Repositories\EloquentLeadScoreRepository;
use App\Repositories\EloquentWebsiteAuditRepository;
use App\Services\LeadDiscovery\GooglePlacesLeadDiscoveryProvider;
use App\Services\LeadScoring\WeightedLeadScoringEngine;
use App\Services\Hosting\NativeDnsResolver;
use App\Services\Hosting\NativeSslInspector;
use App\Services\Hosting\PhpseclibSshCommandRunner;
use App\Services\Hosting\WhmCpanelUapiClient;
use App\Services\WebsiteAnalysis\DeterministicWebsiteAnalyzer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebsiteAnalyzer::class, DeterministicWebsiteAnalyzer::class);
        $this->app->bind(WebsiteAuditRepository::class, EloquentWebsiteAuditRepository::class);
        $this->app->bind(LeadScoringEngine::class, WeightedLeadScoringEngine::class);
        $this->app->bind(LeadScoreRepository::class, EloquentLeadScoreRepository::class);
        $this->app->bind(LeadDiscoveryProvider::class, GooglePlacesLeadDiscoveryProvider::class);
        $this->app->bind(SshCommandRunner::class, PhpseclibSshCommandRunner::class);
        $this->app->bind(CpanelUapiClient::class, WhmCpanelUapiClient::class);
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
        $this->app->bind(SslInspector::class, NativeSslInspector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
