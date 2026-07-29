<?php

namespace App\Http\Controllers;

use App\Actions\Leads\ConvertLeadToCustomer;
use App\Actions\WebsiteAudits\StartWebsiteAudit;
use App\Enums\LeadPipelineStage;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LeadIntelligenceResource;
use App\Http\Resources\WebsiteAuditResource;
use App\Models\Business;
use App\Models\User;
use App\Services\Sales\NextActionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class BusinessController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'grade' => ['nullable', 'in:hot,warm,cool,cold'], 'status' => ['nullable', Rule::in([...LeadPipelineStage::values(), 'reviewing', 'converted', 'disqualified'])],
            'source' => ['nullable', 'string', 'max:50'], 'search' => ['nullable', 'string', 'max:100'],
            'contacted' => ['nullable', 'boolean'],
            'contacted_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return BusinessResource::collection(Business::query()->with(['currentLeadScore', 'contactedBy:id,name'])
            ->when($validated['grade'] ?? null, fn ($query, $grade) => $query->where('lead_grade', $grade))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when(array_key_exists('contacted', $validated), fn ($query) => $validated['contacted']
                ? $query->whereNotNull('contacted_at')
                : $query->whereNull('contacted_at'))
            ->when($validated['contacted_by_user_id'] ?? null, fn ($query, $userId) => $query->where('contacted_by_user_id', $userId))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%')))
            ->orderByRaw('lead_score IS NULL')->orderByDesc('lead_score')->latest()->paginate($validated['per_page'] ?? 20));
    }

    public function contactors(): JsonResponse
    {
        $userIds = Business::query()
            ->whereNotNull('contacted_at')
            ->whereNotNull('contacted_by_user_id')
            ->distinct()
            ->pluck('contacted_by_user_id');

        return response()->json([
            'data' => User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): BusinessResource
    {
        $business = Business::query()->create(['public_id' => (string) Str::ulid(), 'owner_user_id' => $request->user()->id] + $this->validated($request));

        return new BusinessResource($business);
    }

    public function show(Business $business): BusinessResource
    {
        return new BusinessResource($business->load(['currentLeadScore', 'contactedBy:id,name']));
    }

    public function intelligence(Business $business): LeadIntelligenceResource
    {
        $business->load([
            'currentLeadScore',
            'contactedBy:id,name',
            'websiteAudits' => fn ($query) => $query->with(['findings', 'seoAudit', 'performanceAudit', 'accessibilityAudit', 'securityAudit'])
                ->latest('created_at')->limit(10),
        ]);

        return new LeadIntelligenceResource($business);
    }

    public function audit(Request $request, Business $business, StartWebsiteAudit $action): JsonResponse
    {
        abort_unless($business->website_url, 422, 'This lead does not have a website URL.');
        $audit = $action->execute($business->website_url, null, $business->id, $request->user()->id);

        return (new WebsiteAuditResource($audit))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function update(Request $request, Business $business, NextActionValidator $nextActions): BusinessResource
    {
        $data = $this->validated($request, true);
        if (array_key_exists('status', $data) || array_key_exists('next_action_at', $data)) {
            $nextActions->business(['status' => $data['status'] ?? $business->status, 'next_action_at' => $data['next_action_at'] ?? $business->next_action_at], $business->status);
        }
        $business->update($data);

        return new BusinessResource($business->fresh(['currentLeadScore', 'contactedBy:id,name']));
    }

    public function markContacted(Request $request, Business $business): BusinessResource
    {
        $data = $request->validate(['contacted' => ['required', 'boolean']]);
        $business->update([
            'contacted_at' => $data['contacted'] ? now() : null,
            'contacted_by_user_id' => $data['contacted'] ? $request->user()->id : null,
        ]);

        return new BusinessResource($business->fresh(['currentLeadScore', 'contactedBy:id,name']));
    }

    public function convert(Request $request, Business $business, ConvertLeadToCustomer $converter): CustomerResource
    {
        return new CustomerResource($converter->execute($business, $request->user()->id)->load('websites'));
    }

    public function destroy(Business $business): JsonResponse
    {
        $business->delete();

        return response()->json(['message' => 'Lead deleted.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:200'], 'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'google_place_id' => ['nullable', 'string', 'max:191', 'unique:businesses,google_place_id'.($partial ? ','.$request->route('business')->id : '')],
            'google_rating' => ['nullable', 'numeric', 'between:0,5'], 'google_review_count' => ['nullable', 'integer', 'min:0'],
            'domain_registered_at' => ['nullable', 'date', 'before_or_equal:today'], 'design_quality_score' => ['nullable', 'numeric', 'between:0,100'],
            'professionalism_score' => ['nullable', 'numeric', 'between:0,100'], 'missing_features' => ['nullable', 'array', 'max:50'],
            'missing_features.*' => ['string', 'max:100'],
            'status' => ['sometimes', Rule::in(LeadPipelineStage::values())],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'], 'next_action_type' => ['nullable', 'string', 'max:50'],
            'next_action_at' => ['nullable', 'date'], 'next_action_notes' => ['nullable', 'string', 'max:5000'],
            'estimated_project_value' => ['nullable', 'numeric', 'min:0'], 'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'], 'service_sought' => ['nullable', 'string', 'max:100'], 'source' => ['sometimes', 'nullable', 'string', 'max:50'],
            'proposal_id' => ['nullable', 'integer', 'exists:proposals,id'], 'lost_reason' => ['nullable', 'string', 'max:50'], 'competitor_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
