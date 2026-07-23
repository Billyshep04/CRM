<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeWebsite;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebsiteAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_queue_and_retrieve_a_historical_audit(): void
    {
        config()->set('website-audits.enforce_public_networks', false);
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'staff')->firstOrFail());

        $response = $this->actingAs($user)->postJson('/api/website-audits', [
            'url' => 'https://example.com',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_url', 'https://example.com');

        Queue::assertPushed(AnalyzeWebsite::class);
        $publicId = $response->json('data.id');

        $this->actingAs($user)->getJson('/api/website-audits/'.$publicId)
            ->assertOk()
            ->assertJsonPath('data.id', $publicId)
            ->assertJsonPath('data.version', 1);
    }

    public function test_private_network_urls_are_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        $this->actingAs($user)->postJson('/api/website-audits', [
            'url' => 'http://127.0.0.1/admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('url');
    }
}
