<?php

namespace App\Jobs;

use App\Models\WebsiteLaunchRun;
use App\Services\Hosting\WebsiteLaunchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebsiteLaunch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(WebsiteLaunchService $service): void
    {
        if ($run = WebsiteLaunchRun::find($this->runId)) $service->process($run);
    }
}
