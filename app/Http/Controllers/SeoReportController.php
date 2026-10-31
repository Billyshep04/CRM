<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SeoReport;
use App\Services\SeoReportMailer;
use App\Services\SeoReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeoReportController extends Controller
{
    public function index(Request $request)
    {
        $query = SeoReport::query()
            ->with('customer:id,name,email')
            ->orderByDesc('report_month')
            ->orderByDesc('id');

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $attributes = $this->validatedAttributes($request);

        $report = SeoReport::create([
            ...$attributes,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $report->load('customer:id,name,email')], 201);
    }

    public function show(SeoReport $seoReport)
    {
        return response()->json(['data' => $seoReport->load('customer:id,name,email')]);
    }

    public function update(Request $request, SeoReport $seoReport)
    {
        $seoReport->update($this->validatedAttributes($request));

        return response()->json(['data' => $seoReport->fresh()->load('customer:id,name,email')]);
    }

    public function destroy(SeoReport $seoReport)
    {
        $seoReport->delete();

        return response()->json(['message' => 'SEO report deleted.']);
    }

    public function download(Request $request, SeoReport $seoReport, SeoReportPdfService $pdfService)
    {
        $pdf = $pdfService->render($seoReport);

        return $request->boolean('inline')
            ? $pdf->stream($seoReport->downloadFileName())
            : $pdf->download($seoReport->downloadFileName());
    }

    /**
     * Email the saved report to the client. Only ever called from the Send button, never on save.
     */
    public function send(SeoReport $seoReport, SeoReportMailer $mailer)
    {
        try {
            $sentTo = $mailer->send($seoReport);
        } catch (Throwable $exception) {
            Log::error('SEO report email send failed', [
                'seo_report_id' => $seoReport->id,
                'report_number' => $seoReport->report_number,
                'customer_id' => $seoReport->customer_id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'send' => [$exception->getMessage() === 'Customer email is missing.'
                    ? 'Add a client contact email to the report, or an email to the customer, before sending.'
                    : 'SEO report email could not be sent. Check the mail settings.'],
            ]);
        }

        $seoReport->forceFill(['sent_at' => now(), 'sent_to' => $sentTo])->save();

        return response()->json([
            'message' => "SEO report sent to {$sentTo}.",
            'data' => $seoReport->fresh()->load('customer:id,name,email'),
        ]);
    }

    /**
     * Render the PDF from unsaved form data so the report can be checked before saving.
     */
    public function preview(Request $request, SeoReportPdfService $pdfService)
    {
        $report = new SeoReport($this->validatedAttributes($request));

        return $pdfService->render($report)->stream($report->downloadFileName());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(Request $request): array
    {
        $number = ['nullable', 'numeric', 'min:0', 'max:999999999'];
        $text = ['nullable', 'string', 'max:255'];

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'report_number' => ['nullable', 'string', 'max:50'],
            'report_month' => ['required', 'date_format:Y-m'],
            'issue_date' => ['required', 'date'],
            'website_domain' => ['required', 'string', 'max:255'],

            'data' => ['required', 'array'],
            'data.contact_name' => $text,
            'data.contact_email' => $text,
            'data.campaign_month' => ['nullable', 'integer', 'min:1', 'max:999'],
            'data.campaign_length' => ['nullable', 'integer', 'min:1', 'max:999'],
            'data.summary' => ['nullable', 'string', 'max:600'],

            'data.metrics' => ['nullable', 'array'],
            'data.metrics.*' => ['nullable', 'array'],
            'data.metrics.*.current' => $number,
            'data.metrics.*.previous' => $number,

            'data.key_wins' => ['nullable', 'array', 'max:3'],
            'data.key_wins.*.title' => ['nullable', 'string', 'max:90'],
            'data.key_wins.*.detail' => ['nullable', 'string', 'max:160'],

            'data.traffic_history' => ['nullable', 'array', 'max:10'],
            'data.traffic_history.*' => $number,
            'data.growth_since_start' => ['nullable', 'numeric', 'min:-100', 'max:100000'],

            'data.landing_pages' => ['nullable', 'array', 'max:5'],
            'data.landing_pages.*.path' => ['nullable', 'string', 'max:80'],
            'data.landing_pages.*.sessions' => $number,
            'data.landing_pages.*.previous' => $number,

            'data.ranking_buckets' => ['nullable', 'array'],
            'data.ranking_buckets.*' => ['nullable', 'array'],
            'data.ranking_buckets.*.current' => $number,
            'data.ranking_buckets.*.previous' => $number,

            'data.keywords' => ['nullable', 'array', 'max:10'],
            'data.keywords.*.keyword' => ['nullable', 'string', 'max:60'],
            'data.keywords.*.searches' => $number,
            'data.keywords.*.previous' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'data.keywords.*.current' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'data.rankings_note' => ['nullable', 'string', 'max:450'],

            'data.health' => ['nullable', 'array'],
            'data.health.current' => ['nullable', 'integer', 'min:0', 'max:100'],
            'data.health.previous' => ['nullable', 'integer', 'min:0', 'max:100'],
            'data.vitals' => ['nullable', 'array'],
            'data.vitals.lcp' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'data.vitals.inp' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'data.vitals.cls' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'data.fixes' => ['nullable', 'array', 'max:4'],
            'data.fixes.*.count' => $number,
            'data.fixes.*.label' => ['nullable', 'string', 'max:40'],

            'data.authority' => ['nullable', 'array'],
            'data.authority.*' => ['nullable', 'array'],
            'data.authority.*.current' => $number,
            'data.authority.*.previous' => $number,
            'data.new_links' => ['nullable', 'array', 'max:5'],
            'data.new_links.*.domain' => ['nullable', 'string', 'max:60'],
            'data.new_links.*.authority' => $number,
            'data.new_links.*.type' => ['nullable', 'string', 'max:40'],

            'data.work_items' => ['nullable', 'array', 'max:6'],
            'data.work_items.*.category' => ['nullable', 'string', 'max:14'],
            'data.work_items.*.title' => ['nullable', 'string', 'max:80'],
            'data.work_items.*.detail' => ['nullable', 'string', 'max:140'],
            'data.priorities' => ['nullable', 'array', 'max:3'],
            'data.priorities.*' => ['nullable', 'string', 'max:90'],

            'data.prepared_by' => ['nullable', 'array'],
            'data.prepared_by.name' => $text,
            'data.prepared_by.phone' => $text,
            'data.prepared_by.email' => $text,
        ], [], [
            'customer_id' => 'customer',
            'website_domain' => 'website',
            'data.summary' => 'this month in brief',
            'data.metrics.*.current' => 'this month\'s figure',
            'data.metrics.*.previous' => 'last month\'s figure',
            'data.key_wins.*.title' => 'key win headline',
            'data.key_wins.*.detail' => 'key win detail',
            'data.traffic_history.*' => 'monthly sessions figure',
            'data.landing_pages.*.path' => 'landing page path',
            'data.ranking_buckets.*.current' => 'this month\'s keyword count',
            'data.ranking_buckets.*.previous' => 'last month\'s keyword count',
            'data.keywords.*.keyword' => 'keyword',
            'data.keywords.*.previous' => 'last month\'s keyword position',
            'data.keywords.*.current' => 'this month\'s keyword position',
            'data.rankings_note' => 'what this means',
            'data.health.current' => 'site health score',
            'data.health.previous' => 'last month\'s health score',
            'data.vitals.lcp' => 'Largest Contentful Paint',
            'data.vitals.inp' => 'Interaction to Next Paint',
            'data.vitals.cls' => 'Cumulative Layout Shift',
            'data.fixes.*.label' => 'issue fixed',
            'data.new_links.*.domain' => 'linking domain',
            'data.work_items.*.category' => 'work tag',
            'data.work_items.*.title' => 'work item',
            'data.work_items.*.detail' => 'work detail',
            'data.priorities.*' => 'priority',
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['report_month'])->startOfMonth();
        $customer = Customer::query()->findOrFail($validated['customer_id']);

        return [
            'customer_id' => $customer->id,
            'report_number' => trim((string) ($validated['report_number'] ?? '')) ?: $this->defaultReportNumber($customer, $month),
            'report_month' => $month->toDateString(),
            'issue_date' => $validated['issue_date'],
            'website_domain' => $this->normaliseDomain($validated['website_domain']),
            'data' => $this->cleanData($validated['data']),
        ];
    }

    private function defaultReportNumber(Customer $customer, Carbon $month): string
    {
        $initials = collect(preg_split('/\s+/', Str::ascii((string) $customer->name)) ?: [])
            ->filter(fn (string $word): bool => preg_match('/^[A-Za-z]/', $word) === 1)
            ->take(3)
            ->map(fn (string $word): string => strtoupper($word[0]))
            ->implode('');

        return 'SEO-'.($initials !== '' ? $initials : 'WS').'-'.$month->format('my');
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', (string) $domain);

        return rtrim((string) $domain, '/');
    }

    /**
     * Drop rows the form left blank so the PDF only shows what was filled in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanData(array $data): array
    {
        $keepRows = function (string $key, string $requiredField) use (&$data): void {
            $data[$key] = array_values(array_filter(
                $data[$key] ?? [],
                fn ($row): bool => is_array($row) && trim((string) ($row[$requiredField] ?? '')) !== ''
            ));
        };

        $keepRows('key_wins', 'title');
        $keepRows('landing_pages', 'path');
        $keepRows('keywords', 'keyword');
        $keepRows('fixes', 'label');
        $keepRows('new_links', 'domain');
        $keepRows('work_items', 'title');

        $data['priorities'] = array_values(array_filter(
            array_map(fn ($item): string => trim((string) $item), $data['priorities'] ?? []),
            fn (string $item): bool => $item !== ''
        ));

        $data['traffic_history'] = array_map(
            fn ($value) => $value === null || $value === '' ? null : (float) $value,
            array_values($data['traffic_history'] ?? [])
        );

        return $data;
    }
}
