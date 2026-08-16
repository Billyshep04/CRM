<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KrystalWebsiteImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_discover_primary_and_addon_domains_and_existing_sites_are_matched(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $server = $this->server();
        Website::create([
            'customer_id' => $customer->id,
            'name' => 'Existing Client',
            'domain' => 'client-one.test',
            'login_url' => 'https://client-one.test',
            'hosting_enabled' => true,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/discover-websites")
            ->assertOk()
            ->assertJsonPath('data.summary.found', 2)
            ->assertJsonPath('data.summary.connected', 1)
            ->assertJsonPath('data.summary.new', 1);

        $domains = collect($response->json('data.domains'))->keyBy('domain');
        $this->assertSame('connected', $domains['client-one.test']['state']);
        $this->assertSame('addon', $domains['client-two.test']['domain_type']);
        $this->assertStringNotContainsString('secret-token', $response->getContent());
    }

    public function test_discovery_does_not_label_an_external_website_as_krystal_hosted(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $server = $this->server();
        $website = Website::create([
            'customer_id' => $customer->id,
            'name' => 'Externally hosted client',
            'domain' => 'client-one.test',
            'login_url' => 'https://client-one.test',
            'hosting_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/discover-websites")
            ->assertOk()
            ->assertJsonPath('data.domains.0.state', 'matched');

        $website->refresh();
        $this->assertFalse($website->hosting_enabled);
        $this->assertNull($website->hosting_account_id);
    }

    public function test_external_hosting_override_survives_future_krystal_scans(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $server = $this->server();
        $website = Website::create([
            'customer_id' => $customer->id,
            'name' => 'Pirate Lures',
            'domain' => 'client-one.test',
            'login_url' => 'https://client-one.test/wp-admin/',
            'hosting_enabled' => true,
        ]);

        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk();
        $this->assertNotNull($website->fresh()->hosting_account_id);

        $this->actingAs($admin)->putJson("/api/websites/{$website->id}", ['hosting_enabled' => false])->assertOk();
        $this->actingAs($admin)->putJson("/api/websites/{$website->id}", ['hosting_enabled' => true])->assertOk();
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk();

        $website->refresh();
        $this->assertNull($website->hosting_account_id);
        $this->assertTrue($website->metadata['hosting_assignment_excluded']);
    }

    public function test_admin_can_import_an_addon_domain_and_repeat_safely(): void
    {
        $admin = $this->user('admin');
        $customer = $this->customer();
        $server = $this->server();
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk();
        $account = HostingAccount::firstOrFail();

        $first = $this->actingAs($admin)->postJson("/api/hosting-accounts/{$account->id}/import-website", [
            'domain' => 'client-two.test',
            'customer_id' => $customer->id,
            'wordpress_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.domain', 'client-two.test')
            ->assertJsonPath('data.hosting_account_id', $account->id);

        $this->assertNotEmpty($first->json('agent_token'));

        $this->actingAs($admin)->postJson("/api/hosting-accounts/{$account->id}/import-website", [
            'domain' => 'client-two.test',
            'customer_id' => $customer->id,
        ])->assertOk()->assertJsonPath('agent_token', null);

        $this->assertDatabaseCount('websites', 1);
        $this->assertDatabaseHas('websites', [
            'domain' => 'client-two.test',
            'customer_id' => $customer->id,
            'hosting_account_id' => $account->id,
            'hosting_enabled' => true,
        ]);
    }

    public function test_import_rejects_domains_outside_the_selected_account_and_non_admins(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user('staff');
        $customer = $this->customer();
        $server = $this->server();
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk();
        $account = HostingAccount::firstOrFail();

        $this->actingAs($admin)->postJson("/api/hosting-accounts/{$account->id}/import-website", [
            'domain' => 'not-on-krystal.test',
            'customer_id' => $customer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('domain');

        $this->actingAs($staff)
            ->postJson("/api/hosting-servers/{$server->id}/discover-websites")
            ->assertForbidden();
        $this->actingAs($staff)->postJson("/api/hosting-accounts/{$account->id}/import-website", [
            'domain' => 'client-two.test',
            'customer_id' => $customer->id,
        ])->assertForbidden();
    }

    private function server(): HostingServer
    {
        return HostingServer::create([
            'name' => 'Krystal Trinity',
            'provider' => 'krystal',
            'api_type' => 'mock',
            'credentials' => ['username' => 'reseller', 'token' => 'secret-token'],
            'metadata' => [
                'mock_accounts' => [[
                    'external_id' => 'sharedaccount',
                    'username' => 'sharedaccount',
                    'primary_domain' => 'client-one.test',
                    'package_name' => 'Trinity',
                    'status' => 'active',
                    'domains' => [
                        ['domain' => 'client-one.test', 'type' => 'primary'],
                        ['domain' => 'client-two.test', 'type' => 'addon'],
                    ],
                ]],
            ],
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Client',
            'email' => fake()->unique()->safeEmail(),
            'billing_address' => '1 Test Road',
        ]);
    }
}
