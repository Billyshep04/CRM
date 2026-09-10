<?php

namespace App\Console\Commands;

use App\Jobs\SyncWebsiteAnalytics;
use App\Models\Website;
use Illuminate\Console\Command;

class SyncWebsitesAnalytics extends Command
{
    protected $signature = 'analytics:sync-websites {--mode=recent : recent|backfill} {--website= : Limit to a single website id}';

    protected $description = 'Queue Google Analytics traffic syncs for websites with a linked GA4 property';

    public function handle(): int
    {
        $mode = $this->option('mode') === 'backfill' ? 'backfill' : 'recent';

        $query = Website::query()
            ->where('google_analytics_enabled', true)
            ->whereNotNull('google_analytics_property_id')
            ->where('google_analytics_property_id', '!=', '');

        if ($website = $this->option('website')) {
            $query->whereKey($website);
        }

        $count = 0;
        $query->pluck('id')->each(function (int $id) use ($mode, &$count): void {
            SyncWebsiteAnalytics::dispatch($id, $mode);
            $count++;
        });

        $this->info("Queued {$count} analytics sync(s) [{$mode}].");

        return self::SUCCESS;
    }
}
