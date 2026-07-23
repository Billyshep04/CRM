<?php

namespace Tests\Feature;

use App\Actions\WebsiteAudits\StartWebsiteAudit;
use App\Contracts\LeadDiscoveryProvider;
use App\Jobs\DiscoverExternalLeads;
use App\Models\Business;
use App\Models\LeadDiscoveryRun;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteAudit;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_external_lead_discovery(): void
    {
        Queue::fake();
        config(['lead-discovery.google_places.api_key' => 'test-key']);

        $this->actingAs($this->admin())->postJson('/api/lead-discovery', [
            'query' => 'plumbers', 'location' => 'Norwich', 'limit' => 20, 'auto_audit' => false,
        ])->assertAccepted()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('lead_discovery_runs', ['query' => 'plumbers', 'location' => 'Norwich']);
        Queue::assertPushed(DiscoverExternalLeads::class);
    }

    public function test_discovery_job_imports_and_refreshes_google_places_without_duplicates(): void
    {
        $admin = $this->admin();
        $provider = new class implements LeadDiscoveryProvider
        {
            public function search(string $query, string $location, int $pageSize = 20, ?string $pageToken = null): array
            {
                return ['places' => [[
                    'id' => 'place-123', 'displayName' => ['text' => 'Norwich Plumbing Ltd'],
                    'formattedAddress' => '1 Market Street, Norwich', 'nationalPhoneNumber' => '01603 000000',
                    'websiteUri' => 'https://www.norwich-plumbing.example/', 'googleMapsUri' => 'https://maps.google.test/place-123',
                    'rating' => 4.8, 'userRatingCount' => 143, 'primaryType' => 'plumber',
                    'location' => ['latitude' => 52.63, 'longitude' => 1.29],
                ]], 'next_page_token' => null];
            }
        };

        $first = $this->createDiscoveryRun($admin);
        (new DiscoverExternalLeads($first->id))->handle($provider, app(StartWebsiteAudit::class));
        $this->assertDatabaseHas('businesses', ['google_place_id' => 'place-123', 'name' => 'Norwich Plumbing Ltd', 'source' => 'google_places']);
        $this->assertSame(1, $first->fresh()->leads_created);

        $second = $this->createDiscoveryRun($admin);
        (new DiscoverExternalLeads($second->id))->handle($provider, app(StartWebsiteAudit::class));
        $this->assertSame(1, Business::query()->count());
        $this->assertSame(1, $second->fresh()->leads_updated);
    }

    public function test_missing_google_places_configuration_returns_an_actionable_error(): void
    {
        config(['lead-discovery.google_places.api_key' => null]);
        $this->actingAs($this->admin())->postJson('/api/lead-discovery', ['query' => 'cafes', 'location' => 'Norwich'])
            ->assertUnprocessable()->assertJsonPath('message', 'Google Places is not configured. Add GOOGLE_PLACES_API_KEY to .env, then run php artisan config:clear.');
    }

    public function test_lead_can_be_marked_contacted_converted_to_customer_and_deleted(): void
    {
        $admin = $this->admin();
        $lead = Business::query()->create([
            'public_id' => (string) Str::ulid(), 'owner_user_id' => $admin->id, 'name' => 'Example Builders',
            'status' => 'new', 'source' => 'google_places', 'website_url' => 'https://example-builders.test',
            'address' => '10 High Street, Exeter', 'phone' => '01392 000000',
        ]);

        $this->actingAs($admin)->patchJson("/api/businesses/{$lead->public_id}/contacted", ['contacted' => true])
            ->assertOk()->assertJsonPath('data.contacted', true);
        $this->assertNotNull($lead->fresh()->contacted_at);

        $response = $this->actingAs($admin)->postJson("/api/businesses/{$lead->public_id}/convert")
            ->assertCreated()->assertJsonPath('data.name', 'Example Builders');
        $customerId = $response->json('data.id');
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'billing_address' => '10 High Street, Exeter']);
        $this->assertDatabaseHas('websites', ['customer_id' => $customerId, 'login_url' => 'https://example-builders.test']);
        $this->assertDatabaseHas('businesses', ['id' => $lead->id, 'customer_id' => $customerId, 'status' => 'converted']);

        $this->actingAs($admin)->deleteJson("/api/businesses/{$lead->public_id}")->assertOk();
        $this->assertSoftDeleted('businesses', ['id' => $lead->id]);
    }

    public function test_discovery_activity_can_be_deleted_without_deleting_imported_leads(): void
    {
        $admin = $this->admin();
        $run = $this->createDiscoveryRun($admin);
        $lead = Business::query()->create([
            'public_id' => (string) Str::ulid(), 'lead_discovery_run_id' => $run->id,
            'owner_user_id' => $admin->id, 'name' => 'Kept Lead', 'status' => 'new', 'source' => 'google_places',
        ]);

        $this->actingAs($admin)->deleteJson("/api/lead-discovery/{$run->public_id}")->assertOk();
        $this->assertSoftDeleted('lead_discovery_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('businesses', ['id' => $lead->id, 'deleted_at' => null]);
    }

    public function test_lead_intelligence_returns_latest_audit_findings_and_history(): void
    {
        $admin = $this->admin();
        $lead = Business::query()->create([
            'public_id' => (string) Str::ulid(), 'owner_user_id' => $admin->id, 'name' => 'Audit Target',
            'status' => 'new', 'source' => 'google_places', 'website_url' => 'https://audit-target.test',
        ]);
        $audit = WebsiteAudit::query()->create([
            'public_id' => (string) Str::ulid(), 'business_id' => $lead->id, 'requested_by_user_id' => $admin->id,
            'version' => 1, 'status' => 'completed', 'requested_url' => $lead->website_url,
            'overall_score' => 48, 'seo_score' => 35, 'performance_score' => 55,
            'accessibility_score' => 60, 'security_score' => 42, 'structured_results' => [], 'completed_at' => now(),
        ]);
        $audit->findings()->create([
            'category' => 'seo', 'check_key' => 'meta_description', 'severity' => 'high', 'status' => 'failed',
            'title' => 'Meta description is missing', 'description' => 'Search results have no useful summary.',
            'recommendation' => 'Write a service-and-location focused meta description.',
        ]);

        $this->actingAs($admin)->getJson("/api/businesses/{$lead->public_id}/intelligence")
            ->assertOk()->assertJsonPath('data.lead.name', 'Audit Target')
            ->assertJsonPath('data.latest_audit.scores.seo', '35.00')
            ->assertJsonPath('data.latest_audit.findings.0.title', 'Meta description is missing')
            ->assertJsonCount(1, 'data.audit_history');
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }

    private function createDiscoveryRun(User $user): LeadDiscoveryRun
    {
        return LeadDiscoveryRun::query()->create([
            'public_id' => (string) Str::ulid(), 'requested_by_user_id' => $user->id, 'provider' => 'test',
            'query' => 'plumbers', 'location' => 'Norwich', 'requested_limit' => 20, 'auto_audit' => false, 'status' => 'pending',
        ]);
    }
}
