<?php

namespace App\Http\Controllers;

use App\Contracts\AnalyticsProvider;
use App\Exceptions\AnalyticsMisconfiguredException;
use App\Jobs\SyncWebsiteAnalytics;
use App\Models\Website;
use App\Services\Analytics\AnalyticsDriver;
use App\Services\Analytics\WebsiteAnalyticsReportBuilder;
use App\Services\Analytics\WebsiteAnalyticsSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class WebsiteAnalyticsController extends Controller
{
    private const MISCONFIGURED_MESSAGE = 'Analytics is not configured correctly on this server. An administrator needs to check the ANALYTICS_DRIVER setting.';

    public function summary(Request $request, Website $website, WebsiteAnalyticsReportBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['28d', '90d', 'month', 'last_month'])],
        ]);

        return response()->json([
            'data' => [
                ...$builder->build($website, $validated['range'] ?? '28d'),
                // Never throws — lets staff see at a glance whether they're
                // looking at live Google Analytics data or the mock driver.
                'driver' => AnalyticsDriver::currentOrUnknown(),
            ],
        ]);
    }

    public function sync(Website $website): JsonResponse
    {
        if (! $website->analyticsConfigured()) {
            return response()->json(['message' => 'This website has no linked Google Analytics property.'], 422);
        }

        try {
            AnalyticsDriver::current();
        } catch (AnalyticsMisconfiguredException $exception) {
            return $this->misconfiguredResponse($exception);
        }

        SyncWebsiteAnalytics::dispatch($website->id, 'recent');

        return response()->json(['message' => 'Analytics sync queued.'], 202);
    }

    public function connect(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'string', 'regex:/^(properties\/)?\d{4,20}$/'],
            'dashboard_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        if (array_key_exists('dashboard_url', $validated)) {
            $website->google_analytics_dashboard_url = $validated['dashboard_url'] ?: null;
        }

        try {
            // Resolved here, inside the try block: constructing WebsiteAnalyticsSync
            // resolves AnalyticsProvider, which throws AnalyticsMisconfiguredException
            // for an invalid ANALYTICS_DRIVER — that must be caught below, not
            // escape as an uncaught 500 during Laravel's method injection.
            $connected = app(WebsiteAnalyticsSync::class)->connect($website, $validated['property_id']);
        } catch (AnalyticsMisconfiguredException $exception) {
            return $this->misconfiguredResponse($exception);
        } catch (Throwable $exception) {
            Log::error('Google Analytics connect failed.', [
                'website_id' => $website->id,
                'exception' => $exception->getMessage(),
            ]);

            // Best-effort status update — a broken schema or DB hiccup here must
            // never turn an already-known failure into an opaque 500.
            try {
                $website->forceFill([
                    'google_analytics_property_id' => trim(str_replace('properties/', '', $validated['property_id'])),
                    'google_analytics_enabled' => false,
                    'google_analytics_status' => 'error',
                    'google_analytics_last_error' => mb_substr($exception->getMessage(), 0, 480),
                ])->save();
            } catch (Throwable) {
                // Swallowed: the caller still gets a clear 502 below either way.
            }

            return response()->json([
                'message' => 'Could not reach Google Analytics. Check the service-account configuration.',
                'detail' => mb_substr($exception->getMessage(), 0, 300),
            ], 502);
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
                'driver' => AnalyticsDriver::currentOrUnknown(),
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

    public function properties(): JsonResponse
    {
        try {
            $provider = app(AnalyticsProvider::class);

            return response()->json([
                'data' => $provider->listProperties(),
                'driver' => AnalyticsDriver::current(),
            ]);
        } catch (AnalyticsMisconfiguredException $exception) {
            return $this->misconfiguredResponse($exception);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Could not list Google Analytics properties.',
                'detail' => mb_substr($exception->getMessage(), 0, 300),
            ], 502);
        }
    }

    private function misconfiguredResponse(AnalyticsMisconfiguredException $exception): JsonResponse
    {
        Log::error('Google Analytics driver is misconfigured.', ['exception' => $exception->getMessage()]);

        return response()->json([
            'message' => self::MISCONFIGURED_MESSAGE,
            'detail' => $exception->getMessage(),
        ], 500);
    }
}
