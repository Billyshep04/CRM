<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadDiscoveryRunResource;
use App\Jobs\DiscoverExternalLeads;
use App\Models\LeadDiscoveryRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LeadDiscoveryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return LeadDiscoveryRunResource::collection(LeadDiscoveryRun::query()->latest()->paginate($validated['per_page'] ?? 20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:200'], 'location' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'], 'auto_audit' => ['nullable', 'boolean'],
        ]);
        if (! config('lead-discovery.google_places.api_key')) {
            return response()->json(['message' => 'Google Places is not configured. Add GOOGLE_PLACES_API_KEY to .env, then run php artisan config:clear.'], 422);
        }
        $run = LeadDiscoveryRun::query()->create([
            'public_id' => (string) Str::ulid(), 'requested_by_user_id' => $request->user()->id,
            'provider' => config('lead-discovery.provider'), 'query' => $data['query'], 'location' => $data['location'],
            'requested_limit' => $data['limit'] ?? 20, 'auto_audit' => $data['auto_audit'] ?? config('lead-discovery.auto_audit', true), 'status' => 'pending',
        ]);
        DiscoverExternalLeads::dispatch($run->id)->afterCommit();

        return (new LeadDiscoveryRunResource($run))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(LeadDiscoveryRun $leadDiscoveryRun): LeadDiscoveryRunResource
    {
        return new LeadDiscoveryRunResource($leadDiscoveryRun);
    }

    public function destroy(LeadDiscoveryRun $leadDiscoveryRun): JsonResponse
    {
        $leadDiscoveryRun->delete();

        return response()->json(['message' => 'Discovery activity deleted.']);
    }
}
