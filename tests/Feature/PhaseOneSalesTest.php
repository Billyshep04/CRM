<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\FollowUps\FollowUpSequenceService;
use App\Services\FollowUps\ProcessDueFollowUps;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseOneSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pipeline_transition_requires_next_action_and_records_history(): void
    {
        $admin = $this->user('admin');
        $lead = $this->lead($admin);
        $this->actingAs($admin)->patchJson("/api/businesses/{$lead->public_id}/stage", ['stage' => 'contacted'])->assertUnprocessable()->assertJsonValidationErrors('next_action_at');
        $this->actingAs($admin)->patchJson("/api/businesses/{$lead->public_id}/stage", ['stage' => 'contacted', 'next_action_at' => now()->addDay()->toIso8601String(), 'next_action_type' => 'call'])->assertOk()->assertJsonPath('data.status', 'contacted');
        $this->assertDatabaseHas('pipeline_stage_transitions', ['business_id' => $lead->id, 'from_stage' => 'new', 'to_stage' => 'contacted']);
        $this->assertDatabaseHas('crm_activities', ['subject_type' => Business::class, 'subject_id' => $lead->id, 'type' => 'status_change']);
    }

    public function test_call_back_creates_one_stable_follow_up_task(): void
    {
        $admin = $this->user('admin');
        $lead = $this->lead($admin);
        $at = now()->addDays(2)->toIso8601String();
        $response = $this->actingAs($admin)->postJson("/api/businesses/{$lead->public_id}/activities", ['type' => 'call', 'outcome' => 'call_back', 'notes' => 'Try after lunch', 'next_action_at' => $at, 'next_action_type' => 'call'])->assertCreated();
        $this->assertDatabaseHas('tasks', ['business_id' => $lead->id, 'source_type' => 'crm_activity', 'source_reference' => $response->json('data.public_id')]);
        $this->assertSame(1, $lead->activities()->count());
        $this->assertNotNull($lead->fresh()->next_action_at);
    }

    public function test_staff_cannot_read_another_owners_activity_or_today_actions(): void
    {
        $owner = $this->user('staff');
        $other = $this->user('staff');
        $lead = $this->lead($owner);
        $this->actingAs($other)->getJson("/api/businesses/{$lead->public_id}/activities")->assertNotFound();
        $this->actingAs($other)->getJson('/api/today')->assertOk()->assertJsonCount(0, 'data.groups.missing_next_action');
    }

    public function test_pipeline_summary_separates_raw_and_weighted_value(): void
    {
        $admin = $this->user('admin');
        $lead = $this->lead($admin);
        $lead->update(['estimated_project_value' => 1000, 'probability' => 60]);
        $this->actingAs($admin)->getJson('/api/pipeline/summary')->assertOk()->assertJsonPath('data.raw_value', 1000)->assertJsonPath('data.weighted_value', 600);
    }

    public function test_follow_up_enrolment_and_due_processing_are_idempotent(): void
    {
        $admin = $this->user('admin');
        $lead = $this->lead($admin);
        $service = app(FollowUpSequenceService::class);
        $first = $service->enrol($lead, 'lead_default', $admin->id);
        $second = $service->enrol($lead, 'lead_default', $admin->id);
        $this->assertSame($first->id, $second->id);
        $this->assertCount(4, $first->executions);
        $first->executions()->first()->update(['due_at' => now()->subMinute()]);
        app(ProcessDueFollowUps::class)->run();
        app(ProcessDueFollowUps::class)->run();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('follow_up_executions', ['id' => $first->executions()->first()->id, 'status' => 'executed']);
    }

    private function user(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $u = User::factory()->create();
        $u->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $u;
    }

    private function lead(User $owner): Business
    {
        return Business::create(['public_id' => (string) Str::ulid(), 'owner_user_id' => $owner->id, 'name' => 'Phase One Ltd', 'status' => 'new', 'source' => 'manual']);
    }
}
