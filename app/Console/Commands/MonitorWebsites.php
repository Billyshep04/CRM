<?php

namespace App\Console\Commands;

use App\Jobs\MonitorWebsite;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MonitorWebsites extends Command
{
    protected $signature = 'websites:monitor {--type=http}';
    protected $description = 'Queue safe health checks for managed or hosted websites';
    public function handle(): int
    {
        Cache::put('website-monitoring:last-enqueued-at', now()->toIso8601String(), now()->addDay());
        Website::query()->where(fn ($q) => $q->where('management_enabled', true)->orWhere('hosting_enabled', true))->where('status', '!=', 'paused')->pluck('id')->each(fn ($id) => MonitorWebsite::dispatch((int) $id, (string) $this->option('type')));
        return self::SUCCESS;
    }
}
