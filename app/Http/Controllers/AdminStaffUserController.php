<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('slug', 'staff');
            })
            ->latest()
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->assignRole('staff');

        return response()->json([
            'message' => 'Staff user created.',
            'data' => $user->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->assertStaffUser($user);

        $tasks = CrmTask::query()->where('assigned_to_user_id', $user->id);
        $completed = (clone $tasks)->where('status', 'completed');
        $completedThisMonth = (clone $completed)->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $recentTasks = (clone $tasks)
            ->with('job.customer')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(static fn (CrmTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at,
                'hours' => $task->hours,
                'minutes' => $task->minutes,
                'job' => $task->job ? [
                    'id' => $task->job->id,
                    'description' => $task->job->description,
                    'customer_name' => $task->job->customer?->name,
                ] : null,
            ]);

        return response()->json(['data' => [
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'summary' => [
                'total_tasks' => (clone $tasks)->count(),
                'pending_tasks' => (clone $tasks)->where('status', 'pending')->count(),
                'in_progress_tasks' => (clone $tasks)->where('status', 'in_progress')->count(),
                'completed_tasks' => (clone $completed)->count(),
                'completed_this_month' => (clone $completedThisMonth)->count(),
                'overdue_tasks' => (clone $tasks)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'total_hours' => $this->taskHours(clone $completed),
                'hours_this_month' => $this->taskHours(clone $completedThisMonth),
            ],
            'recent_tasks' => $recentTasks,
        ]]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->assertStaffUser($user);

        if ((int) $request->user()?->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if (CrmTask::query()->where('assigned_to_user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'This staff user still has task history. Reassign or delete their tasks before deleting the user.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Staff user deleted.']);
    }

    private function assertStaffUser(User $user): void
    {
        abort_unless($user->hasRole('staff'), 404);
    }

    private function taskHours($query): float
    {
        $minutes = $query->get(['hours', 'minutes'])->sum(
            static fn (CrmTask $task): int => $task->totalMinutes()
        );

        return round($minutes / 60, 2);
    }
}
