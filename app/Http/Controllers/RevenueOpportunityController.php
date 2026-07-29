<?php

namespace App\Http\Controllers;

use App\Enums\RevenueOpportunityStatus;
use App\Enums\RevenueOpportunityType;
use App\Http\Resources\RevenueOpportunityResource;
use App\Models\CrmTask;
use App\Models\RevenueOpportunity;
use App\Services\RevenueOpportunities\OpportunityRecommendationService;
use App\Services\Sales\NextActionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RevenueOpportunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(RevenueOpportunityStatus::class)],
            'type' => ['nullable', Rule::enum(RevenueOpportunityType::class)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = RevenueOpportunity::query()->with(['customer', 'website', 'owner'])->latest();
        foreach (['status', 'type', 'customer_id'] as $filter) {
            $query->when($validated[$filter] ?? null, fn ($builder, $value) => $builder->where($filter, $value));
        }
        if (! $request->user()->hasRole('admin')) {
            $query->where('owner_user_id', $request->user()->id);
        }

        return RevenueOpportunityResource::collection($query->paginate($validated['per_page'] ?? 25));
    }

    public function store(Request $request): RevenueOpportunityResource
    {
        $data = $this->validated($request);
        $opportunity = RevenueOpportunity::query()->create([
            ...$data, 'public_id' => (string) Str::ulid(), 'created_by_user_id' => $request->user()->id,
            'owner_user_id' => $data['owner_user_id'] ?? $request->user()->id, 'source' => 'manual',
            'status' => $data['status'] ?? RevenueOpportunityStatus::Identified->value,
            'confidence' => $data['confidence'] ?? 50,
            'estimated_project_value' => $data['estimated_project_value'] ?? 0,
            'estimated_monthly_revenue' => $data['estimated_monthly_revenue'] ?? 0,
        ]);

        return new RevenueOpportunityResource($opportunity->load(['customer', 'website', 'owner']));
    }

    public function show(Request $request, RevenueOpportunity $revenueOpportunity): RevenueOpportunityResource
    {
        $this->authorizeAccess($request, $revenueOpportunity);

        return new RevenueOpportunityResource($revenueOpportunity->load(['customer', 'website', 'owner']));
    }

    public function update(Request $request, RevenueOpportunity $revenueOpportunity, NextActionValidator $nextActions): RevenueOpportunityResource
    {
        $this->authorizeAccess($request, $revenueOpportunity);
        $data = $this->validated($request, true);
        if (array_key_exists('status', $data) || array_key_exists('next_action_at', $data)) {
            $nextActions->opportunity(['status' => $data['status'] ?? $revenueOpportunity->status->value, 'next_action_at' => $data['next_action_at'] ?? $revenueOpportunity->next_action_at], $revenueOpportunity->status);
        }
        if (($data['status'] ?? null) === RevenueOpportunityStatus::Won->value && $revenueOpportunity->status !== RevenueOpportunityStatus::Won) {
            $data['won_at'] = now();
        }
        if (($data['status'] ?? null) === RevenueOpportunityStatus::Lost->value && $revenueOpportunity->status !== RevenueOpportunityStatus::Lost) {
            $data['lost_at'] = now();
        }
        $revenueOpportunity->update($data);

        return new RevenueOpportunityResource($revenueOpportunity->fresh(['customer', 'website', 'owner']));
    }

    public function destroy(Request $request, RevenueOpportunity $revenueOpportunity): JsonResponse
    {
        $this->authorizeAccess($request, $revenueOpportunity);
        $revenueOpportunity->delete();

        return response()->json(['message' => 'Revenue opportunity deleted.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string', 'distinct', 'exists:revenue_opportunities,public_id'],
        ]);
        $opportunities = RevenueOpportunity::query()->whereIn('public_id', $data['ids'])->get();
        $opportunities->each(fn (RevenueOpportunity $opportunity) => $this->authorizeAccess($request, $opportunity));

        DB::transaction(fn () => $opportunities->each->delete());

        return response()->json([
            'message' => $opportunities->count().' revenue opportunities deleted.',
            'deleted' => $opportunities->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $query = RevenueOpportunity::query();
        if (! $request->user()->hasRole('admin')) {
            $query->where('owner_user_id', $request->user()->id);
        }
        $open = (clone $query)->whereIn('status', ['identified', 'qualified', 'proposed']);

        return response()->json([
            'open_count' => (clone $open)->count(),
            'pipeline_project_value' => round((float) (clone $open)->sum('estimated_project_value'), 2),
            'potential_mrr' => round((float) (clone $open)->sum('estimated_monthly_revenue'), 2),
            'weighted_mrr' => round((float) (clone $open)->selectRaw('COALESCE(SUM(estimated_monthly_revenue * confidence / 100), 0) AS total')->value('total'), 2),
            'renewals_due_30_days' => (clone $query)->whereBetween('renewal_due_at', [today(), today()->addDays(30)])->count(),
        ]);
    }

    public function recommend(Request $request, OpportunityRecommendationService $service): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        return response()->json($service->recommend($request->user()->id));
    }

    public function scheduleFollowUp(Request $request, RevenueOpportunity $revenueOpportunity): JsonResponse
    {
        $this->authorizeAccess($request, $revenueOpportunity);
        $data = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $task = CrmTask::query()->create([
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $revenueOpportunity->owner_user_id ?? $request->user()->id,
            'created_by_user_id' => $request->user()->id, 'revenue_opportunity_id' => $revenueOpportunity->id,
            'title' => $data['title'] ?? 'Follow up: '.$revenueOpportunity->title,
            'description' => $data['notes'] ?? $revenueOpportunity->recommendation, 'priority' => 'normal', 'status' => 'pending',
            'due_date' => $data['due_date'], 'hours' => 0, 'minutes' => 0,
        ]);
        $revenueOpportunity->update(['next_action_at' => $data['due_date']]);

        return response()->json(['message' => 'Follow-up task created.', 'task_id' => $task->id], 201);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'customer_id' => [$required, 'integer', 'exists:customers,id'], 'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'], 'type' => [$required, Rule::enum(RevenueOpportunityType::class)],
            'status' => ['sometimes', Rule::enum(RevenueOpportunityStatus::class)], 'title' => [$required, 'string', 'max:200'],
            'recommendation' => ['nullable', 'string'], 'notes' => ['nullable', 'string'], 'confidence' => ['sometimes', 'integer', 'between:0,100'],
            'estimated_project_value' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'estimated_monthly_revenue' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'renewal_due_at' => ['nullable', 'date'], 'next_action_at' => ['nullable', 'date'],
            'next_action_type' => ['nullable', 'string', 'max:50'], 'next_action_notes' => ['nullable', 'string', 'max:5000'],
            'lost_reason' => ['nullable', 'string', 'max:50'], 'competitor_notes' => ['nullable', 'string', 'max:5000'],
            'converted_subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'], 'converted_job_id' => ['nullable', 'integer', 'exists:jobs,id'],
        ]);
    }

    private function authorizeAccess(Request $request, RevenueOpportunity $opportunity): void
    {
        if (! $request->user()->hasRole('admin') && (int) $opportunity->owner_user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
