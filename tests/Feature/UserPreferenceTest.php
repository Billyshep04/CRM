<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tiles_default_to_visible_and_can_be_saved_per_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('dashboard_tiles.revenue', true)
            ->assertJsonPath('dashboard_tiles.costs', true)
            ->assertJsonPath('dashboard_tiles.profit', true)
            ->assertJsonPath('dashboard_tiles.jobs', true)
            ->assertJsonPath('dashboard_tiles.subscriptions', true)
            ->assertJsonPath('dashboard_tiles.potential_mrr', true)
            ->assertJsonPath('dashboard_tiles.pipeline_value', true)
            ->assertJsonPath('dashboard_tiles.open_opportunities', true);

        $dashboardTiles = [
            'revenue' => true,
            'costs' => false,
            'profit' => true,
            'jobs' => false,
            'subscriptions' => true,
            'potential_mrr' => false,
            'pipeline_value' => true,
            'open_opportunities' => false,
        ];

        $this->actingAs($user)->putJson('/api/preferences', [
            'dashboard_tiles' => $dashboardTiles,
        ])->assertOk()->assertJsonPath('dashboard_tiles', $dashboardTiles);

        $this->actingAs($user)->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('dashboard_tiles', $dashboardTiles)
            ->assertJsonPath('monthly_finance_boxes.revenue', true);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('dashboard_tiles.costs', true)
            ->assertJsonPath('dashboard_tiles.jobs', true);
    }
}
