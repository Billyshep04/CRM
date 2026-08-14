<?php

namespace App\Services\Websites;

use App\Models\Website;
use App\Models\WebsiteIncident;

class WebsiteIncidentManager
{
    public function sync(Website $website, string $key, bool $active, string $severity, string $title, ?string $message = null): void
    {
        $incident = WebsiteIncident::query()->where('website_id', $website->id)->where('deduplication_key', $key)->whereNull('resolved_at')->first();
        if ($active) {
            if ($incident) $incident->update(['last_seen_at' => now(), 'severity' => $severity, 'message' => $message]);
            else WebsiteIncident::create(['website_id' => $website->id, 'type' => $key, 'severity' => $severity, 'title' => $title, 'message' => $message, 'deduplication_key' => $key, 'opened_at' => now(), 'last_seen_at' => now()]);
        } elseif ($incident) {
            $incident->update(['resolved_at' => now(), 'last_seen_at' => now()]);
        }
    }
}
