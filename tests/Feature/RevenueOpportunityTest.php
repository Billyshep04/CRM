<?php

namespace Tests\Feature;

use App\Jobs\SendOpportunityFollowUpReminder;
use App\Mail\OpportunityFollowUpReminderMailable;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\RevenueOpportunity;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website;
use App\Services\AdminMailSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevenueOpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_an_opportunity_and_schedule_an_existing_crm_task(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $response = $this->actingAs($admin)->postJson('/api/revenue-opportunities', [
            'customer_id' => $customer->id, 'type' => 'care_plan', 'title' => 'Premium care plan',
            'estimated_project_value' => 150, 'estimated_monthly_revenue' => 125, 'confidence' => 80,
            'recommendation' => 'Protect the website with updates, backups, and support.',
        ])->assertCreated()->assertJsonPath('data.type', 'care_plan');

        $publicId = $response->json('data.id');
        $this->actingAs($admin)->postJson("/api/revenue-opportunities/{$publicId}/follow-up", [
            'due_date' => now()->addWeek()->toDateString(),
            'notes' => 'Discuss the premium support and backup coverage.',
        ])->assertCreated();

        $opportunity = RevenueOpportunity::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertDatabaseHas('tasks', [
            'revenue_opportunity_id' => $opportunity->id,
            'assigned_to_user_id' => $admin->id,
            'status' => 'pending',
            'description' => 'Discuss the premium support and backup coverage.',
        ]);

        $this->actingAs($admin)->putJson("/api/revenue-opportunities/{$publicId}", ['status' => 'won'])
            ->assertOk()->assertJsonPath('data.status', 'won');
        $this->assertNotNull($opportunity->fresh()->won_at);
    }

    public function test_opportunity_can_be_deleted(): void
    {
        $admin = $this->admin();
        $opportunity = RevenueOpportunity::query()->create([
            'public_id' => (string) Str::ulid(), 'customer_id' => $this->customer()->id, 'owner_user_id' => $admin->id,
            'type' => 'seo', 'status' => 'identified', 'title' => 'SEO package', 'confidence' => 50,
            'estimated_project_value' => 0, 'estimated_monthly_revenue' => 299,
        ]);

        $this->actingAs($admin)->deleteJson("/api/revenue-opportunities/{$opportunity->public_id}")->assertOk();
        $this->assertSoftDeleted('revenue_opportunities', ['id' => $opportunity->id]);
    }

    public function test_opportunities_can_be_bulk_deleted_without_deleting_unselected_records(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $opportunities = collect(['SEO package', 'Care plan', 'New website'])->map(fn (string $title) => RevenueOpportunity::query()->create([
            'public_id' => (string) Str::ulid(), 'customer_id' => $customer->id, 'owner_user_id' => $admin->id,
            'type' => 'seo', 'status' => 'identified', 'title' => $title, 'confidence' => 50,
            'estimated_project_value' => 0, 'estimated_monthly_revenue' => 299,
        ]));

        $this->actingAs($admin)->deleteJson('/api/revenue-opportunities/bulk', [
            'ids' => $opportunities->take(2)->pluck('public_id')->all(),
        ])->assertOk()->assertJsonPath('deleted', 2);

        $this->assertSoftDeleted('revenue_opportunities', ['id' => $opportunities[0]->id]);
        $this->assertSoftDeleted('revenue_opportunities', ['id' => $opportunities[1]->id]);
        $this->assertDatabaseHas('revenue_opportunities', ['id' => $opportunities[2]->id, 'deleted_at' => null]);
    }

    public function test_due_follow_up_queues_and_sends_one_administrator_reminder(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = $this->admin();
        $opportunity = RevenueOpportunity::query()->create([
            'public_id' => (string) Str::ulid(), 'customer_id' => $this->customer()->id, 'owner_user_id' => $admin->id,
            'type' => 'hosting', 'status' => 'identified', 'title' => 'Website hosting', 'confidence' => 70,
            'estimated_project_value' => 0, 'estimated_monthly_revenue' => 35,
        ]);
        $task = CrmTask::query()->create([
            'assigned_to_user_id' => $admin->id, 'created_by_user_id' => $admin->id,
            'revenue_opportunity_id' => $opportunity->id, 'title' => 'Follow up: Website hosting',
            'description' => 'Confirm migration timing.', 'priority' => 'normal', 'status' => 'pending',
            'due_date' => today(), 'hours' => 0, 'minutes' => 0,
        ]);

        $this->artisan('opportunities:send-follow-up-reminders')->assertSuccessful();
        Queue::assertPushed(SendOpportunityFollowUpReminder::class, fn ($job) => $job->taskId === $task->id);

        $settings = new class extends AdminMailSettings
        {
            public function smtp2goEnabled(): bool
            {
                return false;
            }
        };
        (new SendOpportunityFollowUpReminder($task->id))->handle($settings);
        Mail::assertSent(OpportunityFollowUpReminderMailable::class, fn ($mail) => $mail->hasTo($admin->email));
        $this->assertNotNull($task->fresh()->reminder_sent_at);

        (new SendOpportunityFollowUpReminder($task->id))->handle($settings);
        Mail::assertSent(OpportunityFollowUpReminderMailable::class, 1);
    }

    public function test_summary_reports_open_mrr_project_value_and_weighted_pipeline(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        RevenueOpportunity::query()->create([
            'public_id' => (string) Str::ulid(), 'customer_id' => $customer->id, 'owner_user_id' => $admin->id,
            'type' => 'seo', 'status' => 'qualified', 'title' => 'SEO growth', 'confidence' => 75,
            'estimated_project_value' => 500, 'estimated_monthly_revenue' => 400,
        ]);

        $this->actingAs($admin)->getJson('/api/revenue-opportunities/summary')->assertOk()
            ->assertJsonPath('open_count', 1)
            ->assertJsonPath('pipeline_project_value', 500)
            ->assertJsonPath('potential_mrr', 400)
            ->assertJsonPath('weighted_mrr', 300);
    }

    public function test_automatic_recommendations_are_idempotent_and_reuse_existing_subscriptions(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        Website::query()->create(['customer_id' => $customer->id, 'name' => 'Main site', 'login_url' => 'https://example.com']);
        Subscription::query()->create([
            'customer_id' => $customer->id, 'description' => 'Managed website hosting', 'monthly_cost' => 30,
            'billing_frequency' => 'monthly', 'start_date' => today(), 'status' => 'active',
        ]);

        $this->actingAs($admin)->postJson('/api/revenue-opportunities/recommend')->assertOk()->assertJsonPath('created', 3);
        $this->actingAs($admin)->postJson('/api/revenue-opportunities/recommend')->assertOk()->assertJsonPath('created', 0);

        $this->assertDatabaseMissing('revenue_opportunities', ['type' => 'hosting']);
        $this->assertDatabaseHas('revenue_opportunities', ['type' => 'seo', 'source' => 'automatic']);
        $this->assertDatabaseCount('revenue_opportunities', 3);
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => fake()->company(), 'email' => fake()->unique()->companyEmail(),
            'billing_address' => fake()->address(),
        ]);
    }
}
