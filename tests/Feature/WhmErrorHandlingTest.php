<?php

namespace Tests\Feature;

use App\Models\HostingServer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhmErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_whm_failures_return_safe_actionable_errors_without_exposing_the_token(): void
    {
        Http::fake([
            'https://whm.example.test:2087/json-api/listaccts*' => Http::response('Forbidden', 403),
        ]);

        $admin = $this->admin();
        $server = HostingServer::create([
            'name' => 'Krystal',
            'api_type' => 'whm',
            'hostname' => 'whm.example.test',
            'credentials' => ['username' => 'reseller', 'token' => 'never-expose-this-token'],
        ]);

        $test = $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/test")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'WHM rejected the reseller username or API token, or the token does not have permission for this action.'
            );

        $this->assertStringNotContainsString('never-expose-this-token', $test->getContent());

        $sync = $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/sync")
            ->assertUnprocessable();

        $this->assertStringNotContainsString('never-expose-this-token', $sync->getContent());
    }

    public function test_whm_metadata_failures_accept_string_result_codes(): void
    {
        Http::fake([
            'https://whm.example.test:2087/json-api/listaccts*' => Http::response([
                'metadata' => [
                    'result' => '0',
                    'reason' => 'The API token lacks list account privileges.',
                ],
            ]),
        ]);

        $admin = $this->admin();
        $server = HostingServer::create([
            'name' => 'Krystal',
            'api_type' => 'whm',
            'hostname' => 'whm.example.test',
            'credentials' => ['username' => 'reseller', 'token' => 'secret-token'],
        ]);

        $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/test")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The API token lacks list account privileges.');
    }

    public function test_a_transient_whm_connection_failure_is_retried_automatically(): void
    {
        Http::fake([
            'https://whm.example.test:2087/json-api/listaccts*' => Http::sequence()
                ->pushFailedConnection('Connection refused')
                ->push(['metadata' => ['result' => 1], 'data' => ['acct' => []]]),
        ]);

        $admin = $this->admin();
        $server = HostingServer::create([
            'name' => 'Krystal',
            'api_type' => 'whm',
            'hostname' => 'whm.example.test',
            'credentials' => ['username' => 'reseller', 'token' => 'secret-token'],
        ]);

        $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/test")
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_whm_connection_failures_still_fail_clearly_once_retries_are_exhausted(): void
    {
        config(['hosting.whm_connection_retries' => 2]);
        Http::fake([
            'https://whm.example.test:2087/json-api/listaccts*' => Http::sequence()
                ->pushFailedConnection('Connection refused')
                ->pushFailedConnection('Connection refused')
                ->pushFailedConnection('Connection refused'),
        ]);

        $admin = $this->admin();
        $server = HostingServer::create([
            'name' => 'Krystal',
            'api_type' => 'whm',
            'hostname' => 'whm.example.test',
            'credentials' => ['username' => 'reseller', 'token' => 'secret-token'],
        ]);

        $this->actingAs($admin)
            ->postJson("/api/hosting-servers/{$server->id}/test")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The CRM could not connect to WHM on port 2087 after retrying. Check the WHM hostname and that outbound HTTPS connections to port 2087 are allowed.'
            );
    }

    public function test_createacct_is_never_automatically_retried_on_a_connection_failure(): void
    {
        Http::fake([
            // createAccount() first reconciles against the existing account
            // list before attempting creation — that lookup is a plain
            // listaccts call and is allowed to retry as normal.
            'https://whm.example.test:2087/json-api/listaccts*' => Http::response([
                'metadata' => ['result' => 1],
                'data' => ['acct' => []],
            ]),
            'https://whm.example.test:2087/json-api/createacct*' => Http::sequence()
                ->pushFailedConnection('Connection refused')
                ->push(['metadata' => ['result' => 1]]),
        ]);

        $server = HostingServer::create([
            'name' => 'Krystal',
            'api_type' => 'whm',
            'hostname' => 'whm.example.test',
            'credentials' => ['username' => 'reseller', 'token' => 'secret-token'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WHM did not return the account-creation result in time. The CRM will check whether the account was created before retrying.');

        app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($server, [
            'username' => 'testacct',
            'domain' => 'example.test',
            'password' => 'x',
            'package_name' => 'default',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
