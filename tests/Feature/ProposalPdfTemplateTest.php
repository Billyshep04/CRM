<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Proposal;
use App\Models\ProposalLineItem;
use App\Models\StoredFile;
use App\Services\ProposalPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProposalPdfTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_proposal_pdf_is_refreshed_to_the_new_template(): void
    {
        Storage::fake('private');
        $proposal = $this->proposal();

        Storage::disk('private')->put('proposals/legacy.pdf', 'old proposal template');
        $legacyFile = StoredFile::query()->create([
            'disk' => 'private',
            'path' => 'proposals/legacy.pdf',
            'original_name' => 'legacy.pdf',
            'mime_type' => 'application/pdf',
            'size' => 21,
            'category' => 'proposal_pdf',
            'checksum' => hash('sha256', 'old proposal template'),
            'is_private' => true,
            'owner_type' => Proposal::class,
            'owner_id' => $proposal->id,
            // No proposal_template_version at all — exactly the state every
            // proposal generated before this fix is in.
            'metadata' => null,
        ]);
        $proposal->forceFill(['pdf_file_id' => $legacyFile->id])->save();

        $rendered = app(ProposalPdfService::class)->generate($proposal->fresh());

        $this->assertSame($legacyFile->id, $rendered->id);
        $this->assertSame(1, (int) data_get($rendered->metadata, 'proposal_template_version'));
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($rendered->path));
    }

    public function test_a_full_proposal_renders_with_notes_terms_answers_and_line_items(): void
    {
        Storage::fake('private');
        $proposal = $this->proposal([
            'notes' => "The following will highlight what is essential for me to take on the project.\n\nHosting: Hosting the website is essential but you do not have to do this through Web Stamp.",
            'terms' => "Brief: Accepting this proposal confirms you are happy with the brief above.\n\nDeposits: The agreed deposit will need to be paid before work can commence.",
            'form_answers' => [
                ['label' => 'Number of pages', 'value' => 5],
                ['label' => 'Ecommerce required?', 'value' => false],
                ['label' => 'Special requirements', 'value' => null],
            ],
        ]);
        ProposalLineItem::query()->create([
            'proposal_id' => $proposal->id,
            'description' => 'Build of Home page',
            'quantity' => 1,
            'unit_price' => 200,
            'total' => 200,
        ]);

        $rendered = app(ProposalPdfService::class)->generate($proposal->fresh(['lineItems']));

        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($rendered->path));
        $this->assertSame(1, (int) data_get($rendered->metadata, 'proposal_template_version'));
    }

    public function test_a_minimal_proposal_with_no_notes_terms_or_answers_still_renders(): void
    {
        Storage::fake('private');
        $proposal = $this->proposal(['notes' => null, 'terms' => null, 'form_answers' => null]);

        $rendered = app(ProposalPdfService::class)->generate($proposal->fresh(['lineItems']));

        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($rendered->path));
    }

    private function proposal(array $overrides = []): Proposal
    {
        $customer = Customer::query()->create([
            'name' => 'Gareth Hides',
            'email' => 'gareth@handiwork.org.uk',
            'billing_address' => '1 Trade Way',
        ]);

        return Proposal::query()->create([...[
            'customer_id' => $customer->id,
            'proposal_number' => 'PROP-20260804-ESN5C2',
            'version' => 2,
            'title' => 'Handiwork Website Rebuild (Essential Proposal)',
            'proposal_type_label' => 'Website Rebuild',
            'issue_date' => '2026-08-10',
            'expiry_date' => '2026-08-24',
            'status' => 'sent',
            'subtotal' => 950,
            'total' => 950,
        ], ...$overrides]);
    }
}
