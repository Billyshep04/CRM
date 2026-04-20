<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProposalResource;
use App\Jobs\SendProposalEmail;
use App\Models\Job;
use App\Models\Proposal;
use App\Models\ProposalLineItem;
use App\Services\ProposalNumberGenerator;
use App\Services\ProposalPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $query = Proposal::query()
            ->with(['customer', 'job', 'lineItems', 'pdfFile'])
            ->latest();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $query->filterByStatus($request->query('status'));

        $perPage = $request->integer('per_page', 15);

        return ProposalResource::collection($query->paginate($perPage));
    }

    public function store(Request $request, ProposalNumberGenerator $numberGenerator)
    {
        $validated = $this->validatePayload($request);

        /** @var Proposal $proposal */
        $proposal = DB::transaction(function () use ($validated, $request, $numberGenerator): Proposal {
            $job = Job::query()
                ->where('id', $validated['job_id'])
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();

            $lineItem = $this->buildLineItem($validated['line_item'], $job);
            $subtotal = $lineItem['total'];

            $proposal = Proposal::create([
                'customer_id' => $validated['customer_id'],
                'job_id' => $job->id,
                'created_by_user_id' => $request->user()?->id,
                'proposal_number' => $numberGenerator->generate(),
                'version' => 1,
                'title' => $validated['title'],
                'issue_date' => $validated['issue_date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $validated['status'] ?? 'draft',
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            $this->replaceLineItems($proposal, [$lineItem]);

            return $proposal->load(['customer', 'job', 'lineItems', 'pdfFile']);
        });

        $this->applyStatusTransitions($proposal, $proposal->status);

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function show(Proposal $proposal)
    {
        return new ProposalResource($proposal->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function update(Request $request, Proposal $proposal)
    {
        $validated = $this->validatePayload($request);

        /** @var Proposal $targetProposal */
        $targetProposal = DB::transaction(function () use ($validated, $proposal, $request): Proposal {
            $editableProposal = $proposal->isLocked()
                ? $this->createDraftVersion($proposal, $request->user()?->id)
                : $proposal;

            $job = Job::query()
                ->where('id', $validated['job_id'])
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();

            $lineItem = $this->buildLineItem($validated['line_item'], $job);
            $subtotal = $lineItem['total'];

            $editableProposal->update([
                'customer_id' => $validated['customer_id'],
                'job_id' => $job->id,
                'title' => $validated['title'],
                'issue_date' => $validated['issue_date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $validated['status'] ?? 'draft',
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'pdf_file_id' => null,
            ]);

            $this->replaceLineItems($editableProposal, [$lineItem]);

            return $editableProposal->load(['customer', 'job', 'lineItems', 'pdfFile']);
        });

        $this->applyStatusTransitions($targetProposal, $targetProposal->status);

        return new ProposalResource($targetProposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function updateStatus(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected'])],
        ]);

        $proposal->forceFill(['status' => $validated['status']])->save();
        $this->applyStatusTransitions($proposal, $validated['status']);

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function send(Proposal $proposal)
    {
        $proposal->forceFill(['status' => 'sent'])->save();
        $this->applyStatusTransitions($proposal, 'sent');

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function createNewVersion(Proposal $proposal, Request $request)
    {
        $newProposal = DB::transaction(function () use ($proposal, $request): Proposal {
            return $this->createDraftVersion($proposal, $request->user()?->id, true);
        });

        return new ProposalResource($newProposal->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function download(Proposal $proposal, ProposalPdfService $pdfService)
    {
        if (!$proposal->pdfFile) {
            $storedFile = $pdfService->generate($proposal);
            $proposal->forceFill(['pdf_file_id' => $storedFile->id])->save();
            $proposal->setRelation('pdfFile', $storedFile);
        }

        return Storage::disk($proposal->pdfFile->disk)->download(
            $proposal->pdfFile->path,
            "Proposal-{$proposal->proposal_number}-v{$proposal->version}.pdf"
        );
    }

    public function destroy(Proposal $proposal)
    {
        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'line_item' => ['required', 'array'],
            'line_item.quantity' => $this->quantityRules('required'),
            'line_item.unit_price' => ['required', 'numeric', 'min:0'],
            'line_item.description' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $lineItemInput
     * @return array<string, mixed>
     */
    private function buildLineItem(array $lineItemInput, Job $job): array
    {
        $description = trim((string) ($lineItemInput['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($job->description ?? ''));
        }

        if ($description === '') {
            throw ValidationException::withMessages([
                'line_item.description' => ['Line item description is required.'],
            ]);
        }

        $quantity = (float) $lineItemInput['quantity'];
        $unitPrice = (float) $lineItemInput['unit_price'];

        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function replaceLineItems(Proposal $proposal, array $lineItems): void
    {
        ProposalLineItem::query()
            ->where('proposal_id', $proposal->id)
            ->delete();

        foreach ($lineItems as $lineItem) {
            ProposalLineItem::create([
                'proposal_id' => $proposal->id,
                'description' => $lineItem['description'],
                'quantity' => $lineItem['quantity'],
                'unit_price' => $lineItem['unit_price'],
                'total' => $lineItem['total'],
            ]);
        }
    }

    private function createDraftVersion(Proposal $source, ?int $createdByUserId, bool $lockSource = false): Proposal
    {
        $source->loadMissing('lineItems');

        if ($lockSource || !$source->isLocked()) {
            $source->forceFill([
                'locked_at' => $source->locked_at ?? now(),
            ])->save();
        }

        $nextVersion = (int) Proposal::query()
            ->where('proposal_number', $source->proposal_number)
            ->max('version');
        $nextVersion++;

        /** @var Proposal $newProposal */
        $newProposal = Proposal::create([
            'customer_id' => $source->customer_id,
            'job_id' => $source->job_id,
            'created_by_user_id' => $createdByUserId ?: $source->created_by_user_id,
            'parent_proposal_id' => $source->id,
            'proposal_number' => $source->proposal_number,
            'version' => $nextVersion,
            'title' => $source->title,
            'issue_date' => $source->issue_date,
            'expiry_date' => $source->expiry_date,
            'status' => 'draft',
            'notes' => $source->notes,
            'terms' => $source->terms,
            'subtotal' => $source->subtotal,
            'total' => $source->total,
            'pdf_file_id' => null,
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'locked_at' => null,
        ]);

        foreach ($source->lineItems as $lineItem) {
            ProposalLineItem::create([
                'proposal_id' => $newProposal->id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'total' => $lineItem->total,
            ]);
        }

        return $newProposal;
    }

    private function applyStatusTransitions(Proposal $proposal, string $status): void
    {
        if ($status === 'sent') {
            $proposal->forceFill([
                'sent_at' => $proposal->sent_at ?? now(),
                'locked_at' => $proposal->locked_at ?? now(),
                'accepted_at' => null,
                'rejected_at' => null,
            ])->save();

            $this->sendProposalEmailNow($proposal);
            return;
        }

        if ($status === 'accepted') {
            $proposal->forceFill([
                'accepted_at' => $proposal->accepted_at ?? now(),
                'rejected_at' => null,
                'locked_at' => $proposal->locked_at ?? now(),
            ])->save();
            return;
        }

        if ($status === 'rejected') {
            $proposal->forceFill([
                'accepted_at' => null,
                'rejected_at' => $proposal->rejected_at ?? now(),
                'locked_at' => $proposal->locked_at ?? now(),
            ])->save();
            return;
        }

        // Draft should remain editable.
        $proposal->forceFill([
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'locked_at' => null,
        ])->save();
    }

    private function sendProposalEmailNow(Proposal $proposal): void
    {
        try {
            SendProposalEmail::dispatchSync($proposal->id);
        } catch (Throwable $exception) {
            Log::error('Proposal email send failed', [
                'proposal_id' => $proposal->id,
                'proposal_number' => $proposal->proposal_number,
                'customer_id' => $proposal->customer_id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'send' => ['Proposal email could not be sent. Check mail settings in .env (MAIL_*).'],
            ]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function quantityRules(string $requiredRule): array
    {
        return [
            $requiredRule,
            'numeric',
            'min:0.5',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                $quantity = (float) $value;
                $scaled = $quantity * 2;
                $isHalfStep = abs($scaled - round($scaled)) < 0.00001;

                if (!$isHalfStep) {
                    $fail("The {$attribute} field must be a whole number or end in .5.");
                }
            },
        ];
    }
}
