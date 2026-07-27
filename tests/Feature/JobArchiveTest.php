<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_can_be_archived_filtered_and_unarchived_without_data_loss(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());
        $customer = Customer::query()->create([
            'name' => 'Archive Test Customer',
            'email' => 'archive-job@example.test',
            'billing_address' => '1 Test Street',
        ]);
        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'description' => 'Historical website project',
            'cost' => 2450,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)->patchJson("/api/jobs/{$job->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);
        $this->assertNotNull($job->fresh()->archived_at);
        $this->assertDatabaseHas('jobs', ['id' => $job->id, 'cost' => 2450, 'status' => 'completed']);

        $this->actingAs($admin)->getJson('/api/jobs')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/api/jobs?archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $job->id);

        $this->actingAs($admin)->patchJson("/api/jobs/{$job->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);
        $this->assertNull($job->fresh()->archived_at);
        $this->actingAs($admin)->getJson('/api/jobs')->assertOk()->assertJsonPath('data.0.id', $job->id);
    }
}
