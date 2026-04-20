<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Proposal;
use App\Models\ProposalLineItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_locked_proposal_creates_new_version(): void
    {
        $staffUser = User::factory()->create();
        $this->assignRole($staffUser, 'staff');
        Sanctum::actingAs($staffUser);

        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
            'created_by_user_id' => $staffUser->id,
        ]);

        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Landing page refresh',
            'cost' => 500,
            'status' => 'invoiced',
        ]);

        $proposal = Proposal::query()->create([
            'customer_id' => $customer->id,
            'job_id' => $job->id,
            'created_by_user_id' => $staffUser->id,
            'proposal_number' => 'PROP-20260420-ABC123',
            'version' => 1,
            'title' => 'Website amendments',
            'issue_date' => '2026-04-20',
            'expiry_date' => '2026-04-30',
            'status' => 'sent',
            'notes' => 'Original notes',
            'terms' => 'Original terms',
            'subtotal' => 500,
            'total' => 500,
            'sent_at' => now()->subDay(),
            'locked_at' => now()->subDay(),
        ]);

        ProposalLineItem::query()->create([
            'proposal_id' => $proposal->id,
            'description' => 'Landing page refresh',
            'quantity' => 1,
            'unit_price' => 500,
            'total' => 500,
        ]);

        $response = $this->putJson("/api/proposals/{$proposal->id}", [
            'customer_id' => $customer->id,
            'job_id' => $job->id,
            'title' => 'Website amendments revised',
            'issue_date' => '2026-04-20',
            'expiry_date' => '2026-05-10',
            'status' => 'draft',
            'notes' => 'Revised notes',
            'terms' => 'Revised terms',
            'line_item' => [
                'description' => 'Landing page refresh + extras',
                'quantity' => 1.5,
                'unit_price' => 500,
            ],
        ])->assertOk();

        $newProposalId = (int) $response->json('data.id');

        $this->assertNotSame($proposal->id, $newProposalId);
        $this->assertDatabaseHas('proposals', [
            'id' => $newProposalId,
            'proposal_number' => 'PROP-20260420-ABC123',
            'version' => 2,
            'status' => 'draft',
            'title' => 'Website amendments revised',
            'subtotal' => 750,
            'total' => 750,
        ]);
        $this->assertDatabaseHas('proposal_line_items', [
            'proposal_id' => $newProposalId,
            'description' => 'Landing page refresh + extras',
            'quantity' => 1.5,
            'unit_price' => 500,
            'total' => 750,
        ]);
    }

    public function test_customer_can_accept_proposal_from_portal(): void
    {
        Mail::fake();

        $customerUser = User::factory()->create([
            'email' => 'tommy@example.test',
        ]);
        $this->assignRole($customerUser, 'customer');

        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
            'user_id' => $customerUser->id,
        ]);

        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Landing page refresh',
            'cost' => 500,
            'status' => 'invoiced',
        ]);

        $proposal = Proposal::query()->create([
            'customer_id' => $customer->id,
            'job_id' => $job->id,
            'proposal_number' => 'PROP-20260420-XYZ123',
            'version' => 1,
            'title' => 'Website amendments',
            'issue_date' => '2026-04-20',
            'expiry_date' => '2026-04-30',
            'status' => 'sent',
            'subtotal' => 500,
            'total' => 500,
            'sent_at' => now()->subDay(),
            'locked_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($customerUser);

        $this->patchJson("/api/portal/proposals/{$proposal->id}/status", [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull($proposal->fresh()->accepted_at);
    }

    private function assignRole(User $user, string $roleSlug): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
