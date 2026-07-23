<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadScoreResource;
use App\Jobs\CalculateLeadScore;
use App\Models\Business;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadScoreController extends Controller
{
    public function index(Business $business): AnonymousResourceCollection
    {
        return LeadScoreResource::collection($business->leadScores()->with(['business', 'websiteAudit', 'profile'])->latest('calculated_at')->paginate(25));
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate(['website_audit_id' => ['nullable', 'string', 'exists:website_audits,public_id'], 'profile_id' => ['nullable', 'string', 'exists:lead_scoring_profiles,public_id']]);
        $audit = isset($validated['website_audit_id'])
            ? WebsiteAudit::query()->where('public_id', $validated['website_audit_id'])->where('business_id', $business->id)->firstOrFail()
            : $business->websiteAudits()->where('status', 'completed')->latest('completed_at')->firstOrFail();
        $profileId = isset($validated['profile_id']) ? LeadScoringProfile::query()->where('public_id', $validated['profile_id'])->value('id') : null;
        CalculateLeadScore::dispatch($business->id, $audit->id, $profileId, $request->user()->id);

        return response()->json(['message' => 'Lead scoring has been queued.'], 202);
    }
}
