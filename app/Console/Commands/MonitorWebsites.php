<?php

namespace App\Console\Commands;

use App\Jobs\MonitorWebsite;
use App\Models\Website;
use Illuminate\Console\Command;

class MonitorWebsites extends Command
{
    protected $signature = 'websites:monitor {--type=http}';
    protected $description = 'Queue safe health checks for managed or hosted websites';
    public function handle(): int
    {
        Website::query()->where(fn ($q) => $q->where('management_enabled', true)->orWhere('hosting_enabled', true))->where('status', '!=', 'paused')->pluck('id')->each(fn ($id) => MonitorWebsite::dispatch((int) $id, (string) $this->option('type')));
        return self::SUCCESS;
    }
}
