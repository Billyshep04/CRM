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
        $this->actingAs($portal)->getJson("/api/portal/websites/{$theirs->id}")->assertNotFound();
    }

    public function test_admin_can_see_all_sites_without_server_secrets(): void
    {
        $admin = $this->user('admin'); $customer = $this->customer();
        $server = HostingServer::create(['name' => 'Server', 'credentials' => ['username' => 'root', 'token' => 'top-secret']]);
        $this->website($customer, ['hosting_server_id' => $server->id, 'agent_token_hash' => hash('sha256', 'agent-secret'), 'agent_token_encrypted' => 'agent-secret']);
        $this->actingAs($admin)->getJson('/api/websites')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['token' => 'top-secret'])->assertJsonMissing(['agent_token_hash' => hash('sha256', 'agent-secret')])->assertJsonMissing(['agent_token_encrypted' => 'agent-secret']);
    }

    public function test_agent_rejects_bad_token_and_accepts_valid_status(): void
    {
        $website = $this->website($this->customer(), ['agent_token_hash' => hash('sha256', 'valid-token'), 'agent_token_encrypted' => 'valid-token']);
        $this->postJson("/api/website-agent/{$website->id}/status", [])->assertUnauthorized();
        $this->withToken('valid-token')->postJson("/api/website-agent/{$website->id}/status", ['wordpress_version' => '6.9', 'plugin_updates' => 2])->assertAccepted();
        $this->assertDatabaseHas('website_health_checks', ['website_id' => $website->id, 'wordpress_version' => '6.9', 'plugin_updates' => 2]);
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
