<?php

namespace App\Http\Controllers;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\EmailSuppression;
use App\Services\EmailMarketing\SubscriberImporter;
use App\Services\EmailMarketing\SuppressionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EmailSubscriberController extends Controller
{
    public function options()
    {
        return response()->json(['consent_sources' => EmailSubscriber::CONSENT_SOURCES]);
    }

    public function index(Request $request, EmailList $emailList)
    {
        $query = $emailList->subscribers()
            ->orderByDesc('email_list_subscriber.created_at')
            ->orderByDesc('email_subscribers.id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if (in_array($status = $request->query('status'), [EmailSubscriber::STATUS_SUBSCRIBED, EmailSubscriber::STATUS_UNSUBSCRIBED, EmailSubscriber::STATUS_BOUNCED], true)) {
            $query->where('status', $status);
        }

        $page = $query->paginate($request->integer('per_page', 25));
        $page->getCollection()->transform(fn (EmailSubscriber $subscriber) => $this->present($subscriber));

        return response()->json($page);
    }

    public function store(Request $request, EmailList $emailList, SubscriberImporter $importer)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:254'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'consent_source' => ['required', Rule::in(array_keys(EmailSubscriber::CONSENT_SOURCES))],
            'consent_note' => ['nullable', 'string', 'max:2000', 'required_if:consent_source,other'],
        ]);

        $result = $importer->addOne($emailList, $data, $data['consent_source'], $data['consent_note'] ?? null, 'manual', $request->user());

        if ($result['outcome'] !== SubscriberImporter::ADDED) {
            throw ValidationException::withMessages(['email' => $this->refusal($result['outcome'], $result['subscriber'])]);
        }

        return response()->json(['data' => $this->present($emailList->subscribers()->find($result['subscriber']->id))], 201);
    }

    public function update(Request $request, EmailSubscriber $emailSubscriber, SuppressionService $suppressions)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:254'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'consent_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['email'] = EmailSubscriber::normaliseEmail($data['email']);
        if ($data['email'] !== $emailSubscriber->email) {
            if (!SubscriberImporter::isValidEmail($data['email'])) {
                throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
            }
            if (EmailSubscriber::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Another subscriber already uses that email address.']);
            }
            if ($suppressions->isSuppressed($data['email'])) {
                throw ValidationException::withMessages(['email' => 'That address has unsubscribed or bounced, so it can\'t be used.']);
            }
        }

        $emailSubscriber->update($data);

        return response()->json(['data' => $this->present($emailSubscriber->fresh())]);
    }

    /**
     * Removes the person from this list only. Their unsubscribe history is kept.
     */
    public function destroy(EmailList $emailList, EmailSubscriber $emailSubscriber)
    {
        $emailList->subscribers()->detach($emailSubscriber->id);

        return response()->json(['message' => 'Removed from list.']);
    }

    public function unsubscribe(Request $request, EmailSubscriber $emailSubscriber, SuppressionService $suppressions)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in([EmailSubscriber::STATUS_UNSUBSCRIBED, EmailSubscriber::STATUS_BOUNCED])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $suppressions->suppress($emailSubscriber->email, $data['reason'], 'admin', $data['note'] ?? null, $request->user());

        return response()->json(['data' => $this->present($emailSubscriber->fresh())]);
    }

    public function resubscribe(Request $request, EmailSubscriber $emailSubscriber, SuppressionService $suppressions)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], [
            'note.required' => 'Add a note saying why this person is being resubscribed.',
        ]);

        $suppressions->resubscribe($emailSubscriber, $data['note'], $request->user());

        return response()->json(['data' => $this->present($emailSubscriber->fresh())]);
    }

    public function importPreview(Request $request, EmailList $emailList, SubscriberImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ], ['file.mimes' => 'Upload a .csv file.', 'file.max' => 'The file must be 5 MB or smaller.']);

        try {
            return response()->json(['data' => $importer->preview($request->file('file'))]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }

    public function import(Request $request, EmailList $emailList, SubscriberImporter $importer)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'has_header' => ['boolean'],
            'mapping.email' => ['required', 'integer', 'min:0'],
            'mapping.first_name' => ['nullable', 'integer', 'min:0'],
            'mapping.last_name' => ['nullable', 'integer', 'min:0'],
            'consent_source' => ['required', Rule::in(array_keys(EmailSubscriber::CONSENT_SOURCES))],
            'consent_note' => ['nullable', 'string', 'max:2000', 'required_if:consent_source,other'],
            'consent_confirmed' => ['accepted'],
        ], [
            'mapping.email.required' => 'Choose which column holds the email address.',
            'consent_confirmed.accepted' => 'Confirm you have consent to email everyone in this file.',
        ]);

        try {
            $summary = $importer->import(
                $emailList,
                $data['token'],
                $data['mapping'],
                $request->boolean('has_header', true),
                $data['consent_source'],
                $data['consent_note'] ?? null,
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['token' => $exception->getMessage()]);
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EmailSubscriber $subscriber): array
    {
        $suppression = $subscriber->status === EmailSubscriber::STATUS_SUBSCRIBED
            ? null
            : EmailSuppression::active()->where('email', $subscriber->email)->latest('id')->first();

        return [
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'status' => $subscriber->status,
            'consent_source' => $subscriber->consent_source,
            'consent_source_label' => EmailSubscriber::CONSENT_SOURCES[$subscriber->consent_source] ?? $subscriber->consent_source,
            'consent_note' => $subscriber->consent_note,
            'created_at' => $subscriber->created_at,
            'added_to_list_at' => $subscriber->pivot?->created_at,
            'added_via' => $subscriber->pivot?->added_via,
            'unsubscribed_at' => $subscriber->unsubscribed_at,
            'bounced_at' => $subscriber->bounced_at,
            'suppression_note' => $suppression?->note,
        ];
    }

    private function refusal(?string $outcome, ?EmailSubscriber $subscriber): string
    {
        return match ($outcome) {
            SubscriberImporter::DUPLICATE => 'That person is already on this list.',
            SubscriberImporter::SKIPPED => $subscriber?->status === EmailSubscriber::STATUS_BOUNCED
                ? 'That address has bounced, so it can\'t be added. Resubscribe it with a note first if it now works.'
                : 'That person has unsubscribed, so they can\'t be re-added. Resubscribe them with a note if they\'ve asked to rejoin.',
            default => 'Enter a valid email address.',
        };
    }
}
