<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\Analytics\WebsiteAnalyticsSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncWebsiteAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $websiteId, public readonly string $mode = 'recent')
    {
        $this->onQueue((string) config('analytics.queue', 'default'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('website-analytics:'.$this->websiteId))->expireAfter($this->timeout + 30)];
    }

    public function handle(WebsiteAnalyticsSync $sync): void
    {
        $website = Website::query()->find($this->websiteId);
        if (! $website || ! $website->analyticsConfigured()) {
            return;
        }

        $this->mode === 'backfill'
            ? $sync->backfill($website)
            : $sync->syncRecent($website);
    }

    public function failed(?Throwable $exception): void
    {
        Website::query()->whereKey($this->websiteId)->update([
            'google_analytics_status' => 'error',
            'google_analytics_last_error' => $exception
                ? mb_substr($exception->getMessage(), 0, 480)
                : 'The analytics sync job failed.',
        ]);
    }
}
