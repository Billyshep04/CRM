<?php

namespace App\Http\Controllers;

use App\Enums\CallOutcome;
use App\Enums\CrmActivityType;
use App\Models\Business;
use App\Models\RevenueOpportunity;
use App\Services\Sales\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function businessIndex(Request $r, Business $business)
    {
        $this->access($r, $business->owner_user_id);

        return response()->json(['data' => $business->activities()->with('createdBy:id,name')->paginate(min($r->integer('per_page', 25), 100))]);
    }

    public function businessStore(Request $r, Business $business, ActivityRecorder $rec)
    {
        $this->access($r, $business->owner_user_id);

        return response()->json(['data' => $rec->record($business, $this->validated($r), $r->user()->id)->load('createdBy:id,name')], 201);
    }

    public function opportunityIndex(Request $r, RevenueOpportunity $revenueOpportunity)
    {
        $this->access($r, $revenueOpportunity->owner_user_id);

        return response()->json(['data' => $revenueOpportunity->activities()->with('createdBy:id,name')->paginate(min($r->integer('per_page', 25), 100))]);
    }

    public function opportunityStore(Request $r, RevenueOpportunity $revenueOpportunity, ActivityRecorder $rec)
    {
        $this->access($r, $revenueOpportunity->owner_user_id);

        return response()->json(['data' => $rec->record($revenueOpportunity, $this->validated($r), $r->user()->id)->load('createdBy:id,name')], 201);
    }

    private function validated(Request $r): array
    {
        return $r->validate(['type' => ['required', Rule::in(CrmActivityType::values())], 'direction' => ['nullable', 'in:inbound,outbound'], 'outcome' => ['nullable', Rule::in(CallOutcome::values())], 'notes' => ['nullable', 'string', 'max:10000'], 'occurred_at' => ['nullable', 'date'], 'next_action_type' => ['nullable', 'string', 'max:50'], 'next_action_at' => ['nullable', 'date', 'after_or_equal:today'], 'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'], 'metadata' => ['nullable', 'array']]);
    }

    private function access(Request $r, ?int $owner): void
    {
        if (! $r->user()->hasRole('admin') && (int) $owner !== $r->user()->id) {
            abort(404);
        }
    }
}
