<?php

namespace App\Http\Controllers;

use App\Models\FollowUpEnrolment;
use App\Models\FollowUpExecution;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function index(Request $r)
    {
        $q = FollowUpEnrolment::with(['sequence', 'executions.step', 'subject'])->latest();
        if (! $r->user()->hasRole('admin')) {
            $q->where('enrolled_by_user_id', $r->user()->id);
        }

return response()->json(['data' => $q->paginate(min($r->integer('per_page', 25), 100))]);
    }

    public function updateExecution(Request $r, FollowUpExecution $execution)
    {
        $this->access($r, $execution->enrolment);
        $d = $r->validate(['action' => ['required', 'in:skip,reschedule'], 'due_at' => ['required_if:action,reschedule', 'nullable', 'date', 'after_or_equal:now']]);
        $execution->update($d['action'] === 'skip' ? ['status' => 'skipped', 'executed_at' => now()] : ['status' => 'pending', 'due_at' => $d['due_at'], 'failure_message' => null]);

        return response()->json(['data' => $execution->fresh()]);
    }

    public function cancel(Request $r, FollowUpEnrolment $enrolment)
    {
        $this->access($r, $enrolment);
        $enrolment->update(['status' => 'cancelled', 'ended_at' => now()]);
        $enrolment->executions()->where('status', 'pending')->update(['status' => 'cancelled']);

        return response()->json(['data' => $enrolment->fresh()]);
    }

    private function access(Request $r, FollowUpEnrolment $e): void
    {
        if (! $r->user()->hasRole('admin') && (int) $e->enrolled_by_user_id !== $r->user()->id) {
            abort(404);
        }
    }
}
