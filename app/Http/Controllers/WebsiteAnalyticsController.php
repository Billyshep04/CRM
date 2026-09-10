<?php

namespace App\Http\Controllers;

use App\Contracts\AnalyticsProvider;
use App\Jobs\SyncWebsiteAnalytics;
use App\Models\Website;
use App\Services\Analytics\WebsiteAnalyticsReportBuilder;
use App\Services\Analytics\WebsiteAnalyticsSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class WebsiteAnalyticsController extends Controller
{
    public function summary(Request $request, Website $website, WebsiteAnalyticsReportBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['28d', '90d', 'month', 'last_month'])],
        ]);

        return response()->json([
            'data' => $builder->build($website, $validated['range'] ?? '28d'),
        ]);
    }

    public function sync(Website $website): JsonResponse
    {
        if (! $website->analyticsConfigured()) {
            return response()->json(['message' => 'This website has no linked Google Analytics property.'], 422);
        }

        SyncWebsiteAnalytics::dispatch($website->id, 'recent');

        return response()->json(['message' => 'Analytics sync queued.'], 202);
    }

    public function connect(Request $request, Website $website, WebsiteAnalyticsSync $sync): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'string', 'regex:/^(properties\/)?\d{4,20}$/'],
            'dashboard_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        if (array_key_exists('dashboard_url', $validated)) {
            $website->google_analytics_dashboard_url = $validated['dashboard_url'] ?: null;
        }

        try {
            $connected = $sync->connect($website, $validated['property_id']);
        } catch (Throwable $exception) {
            $website->forceFill([
                'google_analytics_property_id' => trim(str_replace('properties/', '', $validated['property_id'])),
                'google_analytics_enabled' => false,
                'google_analytics_status' => 'error',
                'google_analytics_last_error' => mb_substr($exception->getMessage(), 0, 480),
            ])->save();

            return response()->json(['message' => 'Could not reach Google Analytics. Check the service-account configuration.'], 502);
        }

        if ($connected) {
            SyncWebsiteAnalytics::dispatch($website->id, 'backfill');
        }

        return response()->json([
            'data' => [
                'connected' => $connected,
                'status' => $website->fresh()->google_analytics_status,
                'property_id' => $website->google_analytics_property_id,
                'last_error' => $website->google_analytics_last_error,
            ],
        ], $connected ? 200 : 422);
    }

    public function disconnect(Website $website): JsonResponse
    {
        $website->forceFill([
            'google_analytics_enabled' => false,
            'google_analytics_status' => null,
            'google_analytics_last_error' => null,
            'google_analytics_last_synced_at' => null,
        ])->save();

        return response()->json(['data' => ['status' => null]]);
    }

    public function properties(AnalyticsProvider $provider): JsonResponse
    {
        try {
            return response()->json(['data' => $provider->listProperties()]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Could not list Google Analytics properties.',
                'detail' => mb_substr($exception->getMessage(), 0, 300),
            ], 502);
        }
    }
}
