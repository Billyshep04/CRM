<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerFormRequest;
use App\Services\CustomerFormNotificationService;
use App\Services\CustomerFormSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerFormController extends Controller
{
    public function index(Customer $customer, CustomerFormSettings $formSettings)
    {
        return response()->json([
            'templates' => collect($formSettings->adminPayload()['types'] ?? [])
                ->map(static fn (array $template): array => [
                    'slug' => $template['slug'] ?? '',
                    'label' => $template['label'] ?? '',
                    'questions_count' => count($template['questions'] ?? []),
                ])
                ->filter(static fn (array $template): bool => $template['slug'] !== '' && $template['label'] !== '')
                ->values(),
            'data' => $customer->formRequests()
                ->with('sentBy:id,name')
                ->latest('sent_at')
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        Customer $customer,
        CustomerFormSettings $formSettings,
        CustomerFormNotificationService $notifications
    ) {
        $validated = $request->validate([
            'template_slug' => ['required', 'string', 'max:255'],
        ]);

        if (!$customer->user_id) {
            throw ValidationException::withMessages([
                'customer' => ['This customer does not have a portal login. Update the customer record and try again.'],
            ]);
        }

        if (trim((string) $customer->email) === '') {
            throw ValidationException::withMessages([
                'customer' => ['This customer does not have an email address.'],
            ]);
        }

        $template = $formSettings->findType($validated['template_slug']);
        if (!$template) {
            throw ValidationException::withMessages([
                'template_slug' => ['The selected form template no longer exists.'],
            ]);
        }

        $schema = array_values($template['questions'] ?? []);
        if ($schema === []) {
            throw ValidationException::withMessages([
                'template_slug' => ['The selected form template does not contain any fields.'],
            ]);
        }

        $keys = array_map(static fn (array $question): string => (string) ($question['key'] ?? ''), $schema);
        if (count(array_unique($keys)) !== count($keys) || in_array('', $keys, true)) {
            throw ValidationException::withMessages([
                'template_slug' => ['The selected form contains invalid or duplicate field keys.'],
            ]);
        }

        $formRequest = $customer->formRequests()->create([
            'sent_by_user_id' => $request->user()?->id,
            'template_slug' => (string) $template['slug'],
            'template_name' => (string) $template['label'],
            'form_schema' => $schema,
            'status' => CustomerFormRequest::STATUS_PENDING,
            'sent_at' => now(),
        ]);

        $notificationSent = true;
        $warning = null;
        try {
            $notifications->notifyCustomer($formRequest);
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Customer form request created but notification failed.', [
                'customer_form_request_id' => $formRequest->id,
                'customer_id' => $customer->id,
            ]);
            $notificationSent = false;
            $warning = 'The form is available in the customer portal, but the notification email could not be sent.';
        }

        return response()->json([
            'data' => $formRequest->load('sentBy:id,name'),
            'notification_sent' => $notificationSent,
            'warning' => $warning,
        ], 201);
    }

    public function destroy(Customer $customer, CustomerFormRequest $customerFormRequest)
    {
        abort_unless($customerFormRequest->customer_id === $customer->id, 404);

        $customerFormRequest->delete();

        return response()->noContent();
    }
}
