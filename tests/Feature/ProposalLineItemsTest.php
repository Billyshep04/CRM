<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProposalLineItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_can_store_separate_line_items_and_calculated_total(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $user->roles()->syncWithoutDetaching([$role->id]);
        Sanctum::actingAs($user);

        $customer = Customer::query()->create([
            'name' => 'Example Customer',
            'email' => 'customer@example.test',
            'billing_address' => '1 Example Street',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/proposals', [
            'customer_id' => $customer->id,
            'title' => 'Website build',
            'proposal_type' => 'website-build',
            'issue_date' => '2026-08-04',
            'expiry_date' => '2026-08-18',
            'status' => 'draft',
            'form_answers' => ['number_of_pages' => 5],
            'line_items' => [
                ['description' => 'Design', 'quantity' => 2, 'unit_price' => 250],
                ['description' => 'Development', 'quantity' => 3.5, 'unit_price' => 400],
            ],
        ])->assertOk();

        $proposalId = $response->json('data.id');
        $response->assertJsonPath('data.subtotal', 1900)
            ->assertJsonPath('data.total', 1900)
            ->assertJsonCount(2, 'data.line_items');

        $this->assertDatabaseHas('proposal_line_items', [
            'proposal_id' => $proposalId,
            'description' => 'Design',
            'quantity' => 2,
            'unit_price' => 250,
            'total' => 500,
        ]);
        $this->assertDatabaseHas('proposal_line_items', [
            'proposal_id' => $proposalId,
            'description' => 'Development',
            'quantity' => 3.5,
            'unit_price' => 400,
            'total' => 1400,
        ]);
    }

    public function test_legacy_single_line_item_payload_still_works(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $user->roles()->syncWithoutDetaching([$role->id]);
        Sanctum::actingAs($user);

        $customer = Customer::query()->create([
            'name' => 'Existing Integration',
            'email' => 'legacy@example.test',
            'billing_address' => '2 Example Street',
            'created_by_user_id' => $user->id,
        ]);

        $this->postJson('/api/proposals', [
            'customer_id' => $customer->id,
            'title' => 'Legacy proposal',
            'proposal_type' => 'website-build',
            'issue_date' => '2026-08-04',
            'expiry_date' => '2026-08-18',
            'status' => 'draft',
            'form_answers' => ['number_of_pages' => 1],
            'line_item' => ['description' => 'Legacy item', 'quantity' => 1, 'unit_price' => 99],
        ])->assertOk()
            ->assertJsonPath('data.total', 99)
            ->assertJsonCount(1, 'data.line_items');
    }
}
