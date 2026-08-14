<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\Websites\WebsiteMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 2;
    public function __construct(public int $websiteId, public string $type = 'http') {}
    public function handle(WebsiteMonitor $monitor): void { if ($website = Website::find($this->websiteId)) $monitor->check($website, $this->type); }
}
