<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCompletedViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_tasks_are_separated_from_the_active_task_list(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $active = CrmTask::query()->create([
            'assigned_to_user_id' => $staff->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Active task',
            'status' => 'in_progress',
        ]);
        $completed = CrmTask::query()->create([
            'assigned_to_user_id' => $staff->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Completed task',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/tasks?status=all&task_view=current')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)->getJson('/api/tasks?task_view=completed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $completed->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($staff)->getJson('/api/tasks?task_view=current')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($staff)->getJson('/api/tasks?task_view=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
