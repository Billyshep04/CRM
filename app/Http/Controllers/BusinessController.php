<?php

namespace App\Http\Controllers;

use App\Actions\Leads\ConvertLeadToCustomer;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'grade' => ['nullable', 'in:hot,warm,cool,cold'], 'status' => ['nullable', 'in:new,reviewing,qualified,contacted,converted,disqualified'],
            'source' => ['nullable', 'string', 'max:50'], 'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return BusinessResource::collection(Business::query()->with('currentLeadScore')
            ->when($validated['grade'] ?? null, fn ($query, $grade) => $query->where('lead_grade', $grade))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%')))
            ->orderByRaw('lead_score IS NULL')->orderByDesc('lead_score')->latest()->paginate($validated['per_page'] ?? 20));
    }

    public function store(Request $request): BusinessResource
    {
        $business = Business::query()->create(['public_id' => (string) Str::ulid(), 'owner_user_id' => $request->user()->id] + $this->validated($request));

        return new BusinessResource($business);
    }

    public function show(Business $business): BusinessResource
    {
        return new BusinessResource($business->load('currentLeadScore'));
    }

    public function update(Request $request, Business $business): BusinessResource
    {
        $business->update($this->validated($request, true));

        return new BusinessResource($business->fresh('currentLeadScore'));
    }

    public function markContacted(Request $request, Business $business): BusinessResource
    {
        $data = $request->validate(['contacted' => ['required', 'boolean']]);
        $business->update(['contacted_at' => $data['contacted'] ? now() : null]);

        return new BusinessResource($business->fresh('currentLeadScore'));
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
            'status' => ['sometimes', 'in:new,reviewing,qualified,contacted,converted,disqualified'],
        ]);
    }
}
