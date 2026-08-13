<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_staff_overview_and_delete_staff_without_task_history(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');
        $staffWithTasks = $this->userWithRole('staff');
        $unusedStaff = $this->userWithRole('staff');

        CrmTask::query()->create([
            'assigned_to_user_id' => $staffWithTasks->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Pending task',
            'status' => 'pending',
            'due_date' => today()->subDay(),
        ]);
        CrmTask::query()->create([
            'assigned_to_user_id' => $staffWithTasks->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Completed task',
            'status' => 'completed',
            'hours' => 2,
            'minutes' => 30,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->getJson("/api/admin/staff-users/{$staffWithTasks->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_tasks', 2)
            ->assertJsonPath('data.summary.pending_tasks', 1)
            ->assertJsonPath('data.summary.completed_tasks', 1)
            ->assertJsonPath('data.summary.overdue_tasks', 1)
            ->assertJsonPath('data.summary.total_hours', 2.5)
            ->assertJsonCount(2, 'data.recent_tasks');

        $this->actingAs($admin)->deleteJson("/api/admin/staff-users/{$staffWithTasks->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This staff user still has task history. Reassign or delete their tasks before deleting the user.');

        $this->actingAs($admin)->deleteJson("/api/admin/staff-users/{$unusedStaff->id}")
            ->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $unusedStaff->id]);
    }

    public function test_staff_cannot_view_or_delete_staff_account_details(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $staff = $this->userWithRole('staff');
        $otherStaff = $this->userWithRole('staff');

        $this->actingAs($staff)->getJson("/api/admin/staff-users/{$otherStaff->id}")->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/admin/staff-users/{$otherStaff->id}")->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
