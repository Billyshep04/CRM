<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HostingServer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use App\Services\Websites\WebsiteIncidentManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteHostingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(RolePermissionSeeder::class); }

    public function test_portal_customer_only_sees_their_websites_and_visibility_rules(): void
    {
        $portal = $this->user('customer', 'owner@example.com');
        $customer = $this->customer($portal, 'owner@example.com');
        $other = $this->customer(null, 'other@example.com');
        $mine = $this->website($customer, ['portal_visibility' => [...Website::defaultPortalVisibility(), 'technical_details' => false]]);
        $theirs = $this->website($other);
        WebsiteHealthCheck::create(['website_id' => $mine->id, 'checked_at' => now(), 'uptime_status' => 'online', 'ssl_status' => 'valid', 'wordpress_version' => '6.9', 'overall_status' => 'healthy']);

        $this->actingAs($portal)->getJson('/api/portal/websites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)->assertJsonMissing(['wordpress_version' => '6.9']);
        $this->actingAs($portal)->getJson("/api/portal/websites/{$mine->id}")->assertOk()->assertJsonPath('data.id', $mine->id)->assertJsonPath('data.availability', 'Online');
        $this->actingAs($portal)->getJson("/api/portal/websites/{$theirs->id}")->assertNotFound();
    }

    public function test_admin_can_see_all_sites_without_server_secrets(): void
    {
        $admin = $this->user('admin'); $customer = $this->customer();
        $server = HostingServer::create(['name' => 'Server', 'credentials' => ['username' => 'root', 'token' => 'top-secret']]);
        $this->website($customer, ['hosting_server_id' => $server->id, 'agent_token_hash' => hash('sha256', 'agent-secret'), 'agent_token_encrypted' => 'agent-secret']);
        $this->actingAs($admin)->getJson('/api/websites?connection=all')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['token' => 'top-secret'])->assertJsonMissing(['agent_token_hash' => hash('sha256', 'agent-secret')])->assertJsonMissing(['agent_token_encrypted' => 'agent-secret']);
    }

    public function test_main_website_list_only_contains_linked_sites_and_unlinked_has_its_own_view(): void
    {
        $admin = $this->user('admin'); $customer = $this->customer();
        $linked = $this->website($customer, ['agent_last_seen_at' => now()]);
        $unlinked = $this->website($customer, ['name' => 'Unlinked', 'domain' => 'unlinked.example.com', 'login_url' => 'https://unlinked.example.com']);

        $this->actingAs($admin)->getJson('/api/websites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $linked->id)->assertJsonPath('data.0.agent_connected', true);
        $this->actingAs($admin)->getJson('/api/websites?connection=unlinked')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unlinked->id)->assertJsonPath('data.0.agent_linked', false);
    }

    public function test_admin_can_open_a_website_with_health_incidents_and_activity(): void
    {
        $admin = $this->user('admin');
        $website = $this->website($this->customer());
        WebsiteHealthCheck::create(['website_id' => $website->id, 'checked_at' => now(), 'uptime_status' => 'online', 'overall_status' => 'healthy']);
        app(WebsiteIncidentManager::class)->sync($website, 'ssl', true, 'warning', 'SSL warning', 'Certificate needs attention.');

        $this->actingAs($admin)->getJson("/api/websites/{$website->id}")
            ->assertOk()->assertJsonPath('data.id', $website->id)
            ->assertJsonCount(1, 'data.health_checks')->assertJsonCount(1, 'data.incidents');
    }

    public function test_agent_rejects_bad_token_and_accepts_valid_status(): void
    {
        $website = $this->website($this->customer(), ['agent_token_hash' => hash('sha256', 'valid-token'), 'agent_token_encrypted' => 'valid-token']);
        $this->postJson("/api/website-agent/{$website->id}/status", [])->assertUnauthorized();
        $this->withToken('valid-token')->postJson("/api/website-agent/{$website->id}/status", ['wordpress_version' => '6.9', 'plugin_count' => 18, 'plugin_updates' => 2])->assertAccepted();
        $this->assertDatabaseHas('website_health_checks', ['website_id' => $website->id, 'wordpress_version' => '6.9', 'plugin_count' => 18, 'plugin_updates' => 2]);
    }

    public function test_admin_can_generate_a_one_time_connection_token_for_an_unlinked_site(): void
    {
        $admin = $this->user('admin');
        $website = $this->website($this->customer(), ['agent_token_hash' => hash('sha256', 'old-token'), 'agent_token_encrypted' => 'old-token']);

        $token = $this->actingAs($admin)->postJson("/api/websites/{$website->id}/regenerate-agent-token")
            ->assertOk()->assertJsonStructure(['data' => ['agent_token']])->json('data.agent_token');

        $this->assertNotSame('old-token', $token);
        $this->assertSame(hash('sha256', $token), $website->fresh()->agent_token_hash);
        $this->withToken('old-token')->postJson("/api/website-agent/{$website->id}/status", [])->assertUnauthorized();
        $this->withToken($token)->postJson("/api/website-agent/{$website->id}/status", [])->assertAccepted();
    }

    public function test_incidents_are_deduplicated_and_resolved(): void
    {
        $website = $this->website($this->customer()); $manager = app(WebsiteIncidentManager::class);
        $manager->sync($website, 'offline', true, 'critical', 'Offline', 'Unreachable');
        $manager->sync($website, 'offline', true, 'critical', 'Offline', 'Still unreachable');
        $this->assertSame(1, $website->incidents()->count());
        $manager->sync($website, 'offline', false, 'critical', 'Offline');
        $this->assertNotNull($website->incidents()->first()->resolved_at);
    }

    private function user(string $role, ?string $email = null): User { $user = User::factory()->create($email ? ['email' => $email] : []); $user->roles()->attach(Role::where('slug', $role)->firstOrFail()); return $user; }
    private function customer(?User $user = null, string $email = 'customer@example.com'): Customer { return Customer::create(['name' => 'Customer', 'email' => $email, 'billing_address' => '1 Test Road', 'user_id' => $user?->id]); }
    private function website(Customer $customer, array $extra = []): Website { return Website::create([...['customer_id' => $customer->id, 'name' => 'Example', 'domain' => 'example.com', 'login_url' => 'https://example.com', 'management_enabled' => true, 'portal_visibility' => Website::defaultPortalVisibility()], ...$extra]); }
}
