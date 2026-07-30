<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_and_update_organisation_customisation(): void
    {
        $admin = $this->user('admin');
        $settings = $this->actingAs($admin)->getJson('/api/admin/organisation-settings')->assertOk()->json('data');
        $settings['company_name'] = 'Norfolk Digital';
        $settings['primary_colour'] = '#112233';
        $settings['background_colour'] = '#eef2f6';
        $settings['dark_surface_colour'] = '#121820';
        $settings['portal_show_jobs'] = false;

        $this->actingAs($admin)->putJson('/api/admin/organisation-settings', $settings)
            ->assertOk()->assertJsonPath('data.company_name', 'Norfolk Digital')->assertJsonPath('data.portal_show_jobs', false);

        $this->getJson('/api/organisation/public-settings')->assertOk()
            ->assertJsonPath('data.company_name', 'Norfolk Digital')->assertJsonPath('data.primary_colour', '#112233')
            ->assertJsonPath('data.background_colour', '#eef2f6')->assertJsonPath('data.dark_surface_colour', '#121820')
            ->assertJsonMissingPath('data.business_email');
    }

    public function test_staff_cannot_access_organisation_admin_settings(): void
    {
        $this->actingAs($this->user('staff'))->getJson('/api/admin/organisation-settings')->assertForbidden();
    }

    private function user(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
