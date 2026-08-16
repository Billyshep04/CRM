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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
