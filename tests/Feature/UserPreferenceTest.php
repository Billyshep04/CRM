<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_dashboard_tiles_save_when_the_live_schema_column_is_missing(): void
    {
        Schema::table('user_preferences', function ($table): void {
            $table->dropColumn('dashboard_tiles');
        });

        $user = User::factory()->create();
        $dashboardTiles = [
            'revenue' => false,
            'costs' => true,
            'profit' => true,
            'jobs' => false,
            'subscriptions' => true,
            'potential_mrr' => true,
            'pipeline_value' => false,
            'open_opportunities' => true,
        ];

        $this->actingAs($user)->putJson('/api/preferences', [
            'dashboard_tiles' => $dashboardTiles,
        ])->assertOk()->assertJsonPath('dashboard_tiles', $dashboardTiles);

        $this->actingAs($user)->putJson('/api/preferences', [
            'monthly_finance_boxes' => [
                'revenue' => false,
                'costs' => true,
                'profit' => true,
                'tax' => false,
                'owed' => true,
            ],
        ])->assertOk();

        $this->actingAs($user)->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('dashboard_tiles', $dashboardTiles)
            ->assertJsonPath('monthly_finance_boxes.revenue', false)
            ->assertJsonPath('monthly_finance_boxes.tax', false);
    }
}
