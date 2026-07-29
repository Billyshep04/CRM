<?php

namespace App\Http\Controllers;

use App\Enums\LeadPipelineStage;
use App\Enums\LostReason;
use App\Models\Business;
use App\Services\Sales\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineController extends Controller
{
    public function summary(Request $r)
    {
        $q = Business::query();
        if (! $r->user()->hasRole('admin')) {
            $q->where('owner_user_id', $r->user()->id);
        }$rows = $q->selectRaw('status, COUNT(*) count, COALESCE(SUM(estimated_project_value),0) value, COALESCE(SUM(estimated_project_value * probability / 100),0) weighted_value')->groupBy('status')->get();

        return response()->json(['data' => ['stages' => $rows, 'raw_value' => (float) $rows->sum('value'), 'weighted_value' => (float) $rows->sum('weighted_value')]]);
    }

    public function transition(Request $r, Business $business, PipelineService $service)
    {
        if (! $r->user()->hasRole('admin') && (int) $business->owner_user_id !== $r->user()->id) {
            abort(404);
        }$d = $r->validate(['stage' => ['required', Rule::in(LeadPipelineStage::values())], 'next_action_at' => ['nullable', 'date', 'after_or_equal:today'], 'next_action_type' => ['nullable', 'string', 'max:50'], 'next_action_notes' => ['nullable', 'string', 'max:5000'], 'lost_reason' => ['nullable', Rule::in(LostReason::values())], 'competitor_notes' => ['nullable', 'string', 'max:5000']]);

        return response()->json(['data' => $service->transition($business, $d, $r->user()->id)]);
    }
}
