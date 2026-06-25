<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProposalResource;
use App\Jobs\SendProposalEmail;
use App\Models\Job;
use App\Models\Proposal;
use App\Models\ProposalLineItem;
use App\Services\ProposalNumberGenerator;
use App\Services\ProposalPdfService;
use App\Services\ProposalFormSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 15);

        if (!$this->ensureProposalTablesExist(true)) {
            $emptyPaginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return ProposalResource::collection($emptyPaginator);
        }

        $query = Proposal::query()
            ->with(['customer', 'job', 'lineItems', 'pdfFile'])
            ->latest('id');

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $query->filterByStatus($request->query('status'));

        return ProposalResource::collection($query->paginate($perPage));
    }

    public function store(Request $request, ProposalNumberGenerator $numberGenerator, ProposalFormSettings $formSettings)
    {
        $this->assertProposalTablesExist();

        $validated = $this->validatePayload($request, $formSettings);

        $proposal = DB::transaction(function () use ($validated, $request, $numberGenerator): Proposal {
            $lineItem = $this->buildLineItem($validated['line_item'], $validated['title']);
            $subtotal = $lineItem['total'];

            $proposal = Proposal::create([
                'customer_id' => $validated['customer_id'],
                'job_id' => null,
                'created_by_user_id' => $request->user()?->id,
                'proposal_number' => $numberGenerator->generate(),
                'version' => 1,
                'title' => $validated['title'],
                'proposal_type' => $validated['proposal_type'],
                'proposal_type_label' => $validated['proposal_type_label'],
                'form_answers' => $validated['form_answers'],
                'issue_date' => $validated['issue_date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $this->normalizeStatus($validated['status'] ?? 'draft'),
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            $this->replaceLineItems($proposal, [$lineItem]);

            return $proposal->load(['customer', 'job', 'lineItems', 'pdfFile']);
        });

        $this->applyStatusTransitions($proposal, $proposal->status, $request->user()?->id);

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function show(Proposal $proposal)
    {
        $this->assertProposalTablesExist();

        return new ProposalResource($proposal->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function update(Request $request, Proposal $proposal, ProposalFormSettings $formSettings)
    {
        $this->assertProposalTablesExist();

        $validated = $this->validatePayload($request, $formSettings);

        /** @var Proposal $targetProposal */
        $targetProposal = DB::transaction(function () use ($validated, $proposal, $request): Proposal {
            $editableProposal = $proposal->isLocked()
                ? $this->createDraftVersion($proposal, $request->user()?->id)
                : $proposal;

            $lineItem = $this->buildLineItem($validated['line_item'], $validated['title']);
            $subtotal = $lineItem['total'];

            $editableProposal->update([
                'customer_id' => $validated['customer_id'],
                'title' => $validated['title'],
                'proposal_type' => $validated['proposal_type'],
                'proposal_type_label' => $validated['proposal_type_label'],
                'form_answers' => $validated['form_answers'],
                'issue_date' => $validated['issue_date'],
                'expiry_date' => $validated['expiry_date'],
                'status' => $this->normalizeStatus($validated['status'] ?? 'draft'),
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'pdf_file_id' => null,
            ]);

            $this->replaceLineItems($editableProposal, [$lineItem]);

            return $editableProposal->load(['customer', 'job', 'lineItems', 'pdfFile']);
        });

        $this->applyStatusTransitions($targetProposal, $targetProposal->status, $request->user()?->id);

        return new ProposalResource($targetProposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function updateStatus(Request $request, Proposal $proposal)
    {
        $this->assertProposalTablesExist();

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending', 'approved', 'declined', 'sent', 'accepted', 'rejected'])],
        ]);

        $status = $this->normalizeStatus($validated['status']);

        $proposal->forceFill(['status' => $status])->save();
        $this->applyStatusTransitions($proposal, $status, $request->user()?->id);

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function send(Proposal $proposal)
    {
        $this->assertProposalTablesExist();

        $proposal->forceFill(['status' => 'pending'])->save();
        $this->applyStatusTransitions($proposal, 'pending');

        return new ProposalResource($proposal->fresh()->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function createNewVersion(Proposal $proposal, Request $request)
    {
        $this->assertProposalTablesExist();

        $newProposal = DB::transaction(function () use ($proposal, $request): Proposal {
            return $this->createDraftVersion($proposal, $request->user()?->id, true);
        });

        return new ProposalResource($newProposal->load(['customer', 'job', 'lineItems', 'pdfFile']));
    }

    public function download(Proposal $proposal, ProposalPdfService $pdfService)
    {
        $this->assertProposalTablesExist();

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
        $this->assertProposalTablesExist();

        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ProposalFormSettings $formSettings): array
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'proposal_type' => ['required', 'string', 'max:100'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'declined', 'sent', 'accepted', 'rejected'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'form_answers' => ['nullable', 'array'],
            'line_item' => ['required', 'array'],
            'line_item.quantity' => $this->quantityRules('nullable'),
            'line_item.unit_price' => ['required', 'numeric', 'min:0'],
            'line_item.description' => ['nullable', 'string'],
        ]);

        $proposalType = $formSettings->findType((string) $validated['proposal_type']);
        if (!$proposalType) {
            throw ValidationException::withMessages([
                'proposal_type' => ['Please select a valid proposal type.'],
            ]);
        }

        $validated['proposal_type_label'] = $proposalType['label'];
        $validated['form_answers'] = $this->normalizeFormAnswers(
            $proposalType,
            $validated['form_answers'] ?? []
        );

        $validated['line_item']['quantity'] = $validated['line_item']['quantity'] ?? 1;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $lineItemInput
     * @return array<string, mixed>
     */
    private function buildLineItem(array $lineItemInput, string $fallbackTitle): array
    {
        $description = trim((string) ($lineItemInput['description'] ?? ''));
        if ($description === '') {
            $description = trim($fallbackTitle);
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
            'proposal_type' => $source->proposal_type,
            'proposal_type_label' => $source->proposal_type_label,
            'form_answers' => $source->form_answers,
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

    private function applyStatusTransitions(Proposal $proposal, string $status, ?int $userId = null): void
    {
        $status = $this->normalizeStatus($status);

        if ($status === 'pending') {
            $proposal->forceFill([
                'sent_at' => $proposal->sent_at ?? now(),
                'locked_at' => $proposal->locked_at ?? now(),
                'accepted_at' => null,
                'rejected_at' => null,
            ])->save();

            $this->sendProposalEmailNow($proposal);
            return;
        }

        if ($status === 'approved') {
            $proposal->forceFill([
                'accepted_at' => $proposal->accepted_at ?? now(),
                'rejected_at' => null,
                'locked_at' => $proposal->locked_at ?? now(),
            ])->save();

            $this->createApprovedJob($proposal, $userId);
            return;
        }

        if ($status === 'declined') {
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

    private function assertProposalTablesExist(): void
    {
        if ($this->ensureProposalTablesExist(true)) {
            return;
        }

        throw ValidationException::withMessages([
            'proposal' => ['Proposal database tables are missing. Run database migrations, then retry.'],
        ]);
    }

    private function ensureProposalTablesExist(bool $attemptAutoCreate = false): bool
    {
        $proposalsReady = Schema::hasTable('proposals')
            && Schema::hasColumn('proposals', 'customer_id')
            && Schema::hasColumn('proposals', 'job_id')
            && Schema::hasColumn('proposals', 'proposal_number')
            && Schema::hasColumn('proposals', 'version')
            && Schema::hasColumn('proposals', 'title')
            && Schema::hasColumn('proposals', 'proposal_type')
            && Schema::hasColumn('proposals', 'proposal_type_label')
            && Schema::hasColumn('proposals', 'form_answers')
            && Schema::hasColumn('proposals', 'issue_date')
            && Schema::hasColumn('proposals', 'expiry_date')
            && Schema::hasColumn('proposals', 'status')
            && Schema::hasColumn('proposals', 'subtotal')
            && Schema::hasColumn('proposals', 'total')
            && Schema::hasColumn('proposals', 'pdf_file_id')
            && Schema::hasColumn('proposals', 'sent_at')
            && Schema::hasColumn('proposals', 'accepted_at')
            && Schema::hasColumn('proposals', 'rejected_at')
            && Schema::hasColumn('proposals', 'locked_at')
            && Schema::hasColumn('proposals', 'created_at')
            && Schema::hasColumn('proposals', 'updated_at')
            && Schema::hasColumn('proposals', 'deleted_at');

        $lineItemsReady = Schema::hasTable('proposal_line_items')
            && Schema::hasColumn('proposal_line_items', 'proposal_id')
            && Schema::hasColumn('proposal_line_items', 'description')
            && Schema::hasColumn('proposal_line_items', 'quantity')
            && Schema::hasColumn('proposal_line_items', 'unit_price')
            && Schema::hasColumn('proposal_line_items', 'total')
            && Schema::hasColumn('proposal_line_items', 'created_at')
            && Schema::hasColumn('proposal_line_items', 'updated_at');

        if ($proposalsReady && $lineItemsReady) {
            return true;
        }

        if (!$attemptAutoCreate) {
            return false;
        }

        try {
            if (!Schema::hasTable('proposals')) {
                Schema::create('proposals', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                    $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
                    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('parent_proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
                    $table->string('proposal_number');
                    $table->unsignedInteger('version')->default(1);
                    $table->string('title');
                    $table->string('proposal_type')->nullable();
                    $table->string('proposal_type_label')->nullable();
                    $table->json('form_answers')->nullable();
                    $table->date('issue_date');
                    $table->date('expiry_date');
                    $table->string('status')->default('draft');
                    $table->text('notes')->nullable();
                    $table->text('terms')->nullable();
                    $table->decimal('subtotal', 12, 2);
                    $table->decimal('total', 12, 2);
                    $table->foreignId('pdf_file_id')->nullable()->constrained('files')->nullOnDelete();
                    $table->timestamp('sent_at')->nullable();
                    $table->timestamp('accepted_at')->nullable();
                    $table->timestamp('rejected_at')->nullable();
                    $table->timestamp('locked_at')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                    $table->unique(['proposal_number', 'version']);
                });
            } else {
                Schema::table('proposals', function (Blueprint $table): void {
                    if (!Schema::hasColumn('proposals', 'proposal_type')) {
                        $table->string('proposal_type')->nullable()->after('title');
                    }

                    if (!Schema::hasColumn('proposals', 'proposal_type_label')) {
                        $table->string('proposal_type_label')->nullable()->after('proposal_type');
                    }

                    if (!Schema::hasColumn('proposals', 'form_answers')) {
                        $table->json('form_answers')->nullable()->after('proposal_type_label');
                    }
                });
            }

            if (!Schema::hasTable('proposal_line_items')) {
                Schema::create('proposal_line_items', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
                    $table->text('description');
                    $table->decimal('quantity', 8, 2)->default(1);
                    $table->decimal('unit_price', 12, 2);
                    $table->decimal('total', 12, 2);
                    $table->timestamps();
                });
            }
        } catch (Throwable) {
            // Ignore and return false below if schema cannot be updated at runtime.
        }

        $proposalsReady = Schema::hasTable('proposals')
            && Schema::hasColumn('proposals', 'customer_id')
            && Schema::hasColumn('proposals', 'job_id')
            && Schema::hasColumn('proposals', 'proposal_number')
            && Schema::hasColumn('proposals', 'version')
            && Schema::hasColumn('proposals', 'title')
            && Schema::hasColumn('proposals', 'proposal_type')
            && Schema::hasColumn('proposals', 'proposal_type_label')
            && Schema::hasColumn('proposals', 'form_answers')
            && Schema::hasColumn('proposals', 'issue_date')
            && Schema::hasColumn('proposals', 'expiry_date')
            && Schema::hasColumn('proposals', 'status')
            && Schema::hasColumn('proposals', 'subtotal')
            && Schema::hasColumn('proposals', 'total')
            && Schema::hasColumn('proposals', 'pdf_file_id')
            && Schema::hasColumn('proposals', 'sent_at')
            && Schema::hasColumn('proposals', 'accepted_at')
            && Schema::hasColumn('proposals', 'rejected_at')
            && Schema::hasColumn('proposals', 'locked_at')
            && Schema::hasColumn('proposals', 'created_at')
            && Schema::hasColumn('proposals', 'updated_at')
            && Schema::hasColumn('proposals', 'deleted_at');

        $lineItemsReady = Schema::hasTable('proposal_line_items')
            && Schema::hasColumn('proposal_line_items', 'proposal_id')
            && Schema::hasColumn('proposal_line_items', 'description')
            && Schema::hasColumn('proposal_line_items', 'quantity')
            && Schema::hasColumn('proposal_line_items', 'unit_price')
            && Schema::hasColumn('proposal_line_items', 'total')
            && Schema::hasColumn('proposal_line_items', 'created_at')
            && Schema::hasColumn('proposal_line_items', 'updated_at');

        return $proposalsReady && $lineItemsReady;
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

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'sent' => 'pending',
            'accepted' => 'approved',
            'rejected' => 'declined',
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $proposalType
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFormAnswers(array $proposalType, array $input): array
    {
        $answers = [];

        foreach (($proposalType['questions'] ?? []) as $question) {
            $key = (string) ($question['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $value = $input[$key] ?? null;
            $type = (string) ($question['type'] ?? 'text');

            if ($type === 'checkbox') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            if (($question['required'] ?? false) && ($value === null || $value === '')) {
                throw ValidationException::withMessages([
                    "form_answers.{$key}" => ["{$question['label']} is required."],
                ]);
            }

            $answers[] = [
                'key' => $key,
                'label' => (string) ($question['label'] ?? $key),
                'type' => $type,
                'value' => $value,
            ];
        }

        return $answers;
    }

    private function createApprovedJob(Proposal $proposal, ?int $userId = null): void
    {
        if ($proposal->job_id) {
            return;
        }

        $answers = collect($proposal->form_answers ?? [])
            ->map(function (array $answer): string {
                $value = $answer['value'] ?? null;
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                return ($answer['label'] ?? $answer['key'] ?? 'Question') . ': ' . ($value === null || $value === '' ? 'Not specified' : $value);
            })
            ->implode("\n");

        $job = Job::create([
            'customer_id' => $proposal->customer_id,
            'created_by_user_id' => $userId ?: $proposal->created_by_user_id,
            'description' => $proposal->title,
            'notes' => trim("Created automatically from approved proposal {$proposal->proposal_number} v{$proposal->version}.\n\nProposal type: {$proposal->proposal_type_label}\n\n{$answers}\n\n{$proposal->notes}"),
            'cost' => $proposal->total,
            'status' => 'open',
        ]);

        $proposal->forceFill(['job_id' => $job->id])->save();
    }
}
