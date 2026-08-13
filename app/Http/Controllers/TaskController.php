<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrmTaskResource;
use App\Mail\TaskCompletedAdminMailable;
use App\Models\CrmTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->ensureTasksTableExists()) {
            return CrmTaskResource::collection($this->emptyPaginator($request));
        }

        $user = $request->user();
        $query = CrmTask::query()
            ->with(['assignedTo.roles', 'job.customer', 'revenueOpportunity.customer'])
            ->latest('id');

        if (! $user?->hasAnyRole(['admin', 'staff'])) {
            $query->where('assigned_to_user_id', $user?->id);
        }

        $completedView = $request->query('task_view') === 'completed';
        if ($completedView) {
            $query->where('status', 'completed');
        } else {
            $query->where('status', '!=', 'completed');
        }

        if (! $completedView && ($status = $request->query('status'))) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($request->query('staff_id') && $user?->hasAnyRole(['admin', 'staff'])) {
            $query->where('assigned_to_user_id', (int) $request->query('staff_id'));
        }

        if ($request->query('job_id')) {
            $query->where('job_id', (int) $request->query('job_id'));
        }

        return CrmTaskResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request)
    {
        $this->assertAdmin($request);
        $this->assertTasksTableExists();

        $validated = $this->validateAdminPayload($request);

        $task = CrmTask::create([
            ...$validated,
            'created_by_user_id' => $request->user()?->id,
            'status' => $validated['status'] ?? 'pending',
            'hours' => 0,
            'minutes' => 0,
        ]);

        return new CrmTaskResource($task->load(['assignedTo.roles', 'job.customer', 'revenueOpportunity.customer']));
    }

    public function show(Request $request, CrmTask $task)
    {
        $this->authorizeTaskAccess($request, $task);

        return new CrmTaskResource($task->load(['assignedTo.roles', 'job.customer', 'revenueOpportunity.customer']));
    }

    public function update(Request $request, CrmTask $task)
    {
        $this->authorizeTaskAccess($request, $task);
        $wasCompleted = $task->status === 'completed';

        if ($request->user()?->hasAnyRole(['admin', 'staff'])) {
            $validated = $this->validateAdminPayload($request, true);
            $staffPayload = [];
            if ($request->hasAny(['hours', 'minutes', 'staff_notes'])) {
                $staffPayload = $this->validateStaffPayload($request);
                if (($staffPayload['status'] ?? null) === 'completed') {
                    $staffPayload['completed_at'] = $task->completed_at ?? now();
                } elseif (array_key_exists('status', $staffPayload)) {
                    $staffPayload['completed_at'] = null;
                }
            } elseif (($validated['status'] ?? null) === 'completed') {
                $validated['completed_at'] = $task->completed_at ?? now();
            } elseif (array_key_exists('status', $validated)) {
                $validated['completed_at'] = null;
            }

            $task->update([...$validated, ...$staffPayload]);
        } else {
            $validated = $this->validateStaffPayload($request);
            $nextStatus = $validated['status'] ?? $task->status;

            $task->update([
                'status' => $nextStatus,
                'hours' => $validated['hours'] ?? $task->hours,
                'minutes' => $validated['minutes'] ?? $task->minutes,
                'staff_notes' => array_key_exists('staff_notes', $validated) ? $validated['staff_notes'] : $task->staff_notes,
                'completed_at' => $nextStatus === 'completed'
                    ? ($task->completed_at ?? now())
                    : null,
            ]);

        }

        if (! $wasCompleted && $task->fresh()?->status === 'completed') {
            $this->sendCompletionEmail($task->fresh());
        }

        return new CrmTaskResource($task->fresh()->load(['assignedTo.roles', 'job.customer', 'revenueOpportunity.customer']));
    }

    public function destroy(Request $request, CrmTask $task)
    {
        $this->assertAdmin($request);
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        if (! $this->ensureTasksTableExists()) {
            return response()->json($this->emptyDashboard());
        }

        $user = $request->user();
        $query = CrmTask::query();
        if (! $user?->hasRole('admin')) {
            $query->where('assigned_to_user_id', $user?->id);
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $pending = (clone $query)->whereIn('status', ['pending', 'in_progress'])->count();
        $completedThisMonth = (clone $query)->completedBetween($monthStart, $monthEnd)->count();
        $minutesThisMonth = (clone $query)->completedBetween($monthStart, $monthEnd)->get(['hours', 'minutes'])
            ->sum(fn (CrmTask $task): int => $task->totalMinutes());
        $overdue = (clone $query)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_date', '<', today()->toDateString())
            ->count();

        return response()->json([
            'pending_tasks' => $pending,
            'completed_this_month' => $completedThisMonth,
            'hours_this_month' => round($minutesThisMonth / 60, 2),
            'overdue_tasks' => $overdue,
            'total_tasks' => (clone $query)->count(),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        if (! $this->ensureTasksTableExists()) {
            return response()->json($this->emptyMonthlyPayload());
        }

        $staffId = $request->query('staff_id') ? (int) $request->query('staff_id') : null;
        if ($staffId && ! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($this->monthlyPayload(
            $request->user()?->hasRole('admin') ? $staffId : $request->user()?->id
        ));
    }

    public function staffSummary(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        if (! $this->ensureTasksTableExists()) {
            return response()->json(['data' => []]);
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $staffUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'staff'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        $data = $staffUsers->map(function (User $staff) use ($monthStart, $monthEnd): array {
            $base = CrmTask::query()->where('assigned_to_user_id', $staff->id);
            $completedThisMonth = (clone $base)->completedBetween($monthStart, $monthEnd)->count();
            $minutesThisMonth = (clone $base)->completedBetween($monthStart, $monthEnd)->get(['hours', 'minutes'])
                ->sum(fn (CrmTask $task): int => $task->totalMinutes());

            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'pending_tasks' => (clone $base)->whereIn('status', ['pending', 'in_progress'])->count(),
                'completed_this_month' => $completedThisMonth,
                'hours_this_month' => round($minutesThisMonth / 60, 2),
                'total_completed' => (clone $base)->where('status', 'completed')->count(),
                'total_hours' => round((clone $base)->where('status', 'completed')->get(['hours', 'minutes'])->sum(fn (CrmTask $task): int => $task->totalMinutes()) / 60, 2),
            ];
        });

        return response()->json(['data' => $data]);
    }

    private function validateAdminPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'assigned_to_user_id' => [$required, 'integer', 'exists:users,id'],
            'job_id' => ['nullable', 'integer', 'exists:jobs,id'],
            'revenue_opportunity_id' => ['nullable', 'integer', 'exists:revenue_opportunities,id'],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function validateStaffPayload(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'hours' => ['nullable', 'integer', 'min:0', 'max:999'],
            'minutes' => ['nullable', 'integer', Rule::in([0, 15, 30, 45])],
            'staff_notes' => ['nullable', 'string'],
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'staff'])) {
            abort(403);
        }
    }

    private function authorizeTaskAccess(Request $request, CrmTask $task): void
    {
        if ($request->user()?->hasAnyRole(['admin', 'staff'])) {
            return;
        }

        if ((int) $task->assigned_to_user_id !== (int) $request->user()?->id) {
            abort(404);
        }
    }

    private function sendCompletionEmail(CrmTask $task): void
    {
        try {
            Mail::to('info@web-stamp.co.uk')->send(new TaskCompletedAdminMailable($task));
        } catch (Throwable $exception) {
            Log::error('Task completion notification failed', [
                'task_id' => $task->id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function assertTasksTableExists(): void
    {
        if ($this->ensureTasksTableExists()) {
            return;
        }

        throw ValidationException::withMessages([
            'tasks' => ['Task database table is missing. Run database migrations, then retry.'],
        ]);
    }

    private function ensureTasksTableExists(): bool
    {
        return Schema::hasTable('tasks')
            && Schema::hasColumn('tasks', 'assigned_to_user_id')
            && Schema::hasColumn('tasks', 'hours')
            && Schema::hasColumn('tasks', 'minutes');
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $request->integer('per_page', 20), 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function emptyDashboard(): array
    {
        return [
            'pending_tasks' => 0,
            'completed_this_month' => 0,
            'hours_this_month' => 0,
            'overdue_tasks' => 0,
            'total_tasks' => 0,
        ];
    }

    private function emptyMonthlyPayload(): array
    {
        return [
            'selected_month' => now()->startOfMonth()->toDateString(),
            'months' => [],
        ];
    }

    private function monthlyPayload(?int $staffId = null): array
    {
        $now = now();
        $startMonth = Carbon::create($now->year, 3, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
        if ($now->month < 3) {
            $startMonth->subYearNoOverflow();
        }

        $endMonth = $now->copy()->startOfMonth();
        $cursor = $startMonth->copy();
        $months = [];
        $previous = null;
        $totalTasks = 0;
        $totalMinutes = 0;

        while ($cursor->lte($endMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $query = CrmTask::query()->completedBetween($monthStart, $monthEnd);
            if ($staffId) {
                $query->where('assigned_to_user_id', $staffId);
            }

            $tasks = $query->get(['hours', 'minutes']);
            $completed = $tasks->count();
            $minutes = $tasks->sum(fn (CrmTask $task): int => $task->totalMinutes());
            $totalTasks += $completed;
            $totalMinutes += $minutes;

            $months[] = [
                'month_start' => $monthStart->toDateString(),
                'month_end' => $monthEnd->toDateString(),
                'label' => $monthStart->format('F Y'),
                'completed_tasks' => $completed,
                'hours_total' => round($minutes / 60, 2),
                'tasks_change_percent' => $previous ? $this->percentChange($previous['completed_tasks'], $completed) : null,
                'hours_change_percent' => $previous ? $this->percentChange((float) $previous['hours_total'], round($minutes / 60, 2)) : null,
            ];

            $previous = end($months);
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $months[] = [
            'month_start' => 'total',
            'month_end' => 'total',
            'label' => 'Total',
            'completed_tasks' => $totalTasks,
            'hours_total' => round($totalMinutes / 60, 2),
            'tasks_change_percent' => null,
            'hours_change_percent' => null,
        ];

        return [
            'start_month' => $startMonth->toDateString(),
            'end_month' => $endMonth->toDateString(),
            'selected_month' => $endMonth->toDateString(),
            'months' => $months,
        ];
    }

    private function percentChange(float|int $previous, float|int $current): ?float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
