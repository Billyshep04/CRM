<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SocialReport;
use App\Services\SocialReportImageService;
use App\Services\SocialReportMailer;
use App\Services\SocialReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialReportController extends Controller
{
    public function index(Request $request)
    {
        $query = SocialReport::query()
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
        $report = SocialReport::create([
            ...$this->validatedAttributes($request),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $report->load('customer:id,name,email')], 201);
    }

    public function show(SocialReport $socialReport)
    {
        return response()->json(['data' => $socialReport->load('customer:id,name,email')]);
    }

    public function update(Request $request, SocialReport $socialReport)
    {
        $socialReport->update($this->validatedAttributes($request));

        return response()->json(['data' => $socialReport->fresh()->load('customer:id,name,email')]);
    }

    public function destroy(SocialReport $socialReport)
    {
        $socialReport->delete();

        return response()->json(['message' => 'Social media report deleted.']);
    }

    public function download(Request $request, SocialReport $socialReport, SocialReportPdfService $pdfService)
    {
        $pdf = $pdfService->render($socialReport);

        return $request->boolean('inline')
            ? $pdf->stream($socialReport->downloadFileName())
            : $pdf->download($socialReport->downloadFileName());
    }

    /**
     * Render the PDF from unsaved form data so the report can be checked before saving.
     */
    public function preview(Request $request, SocialReportPdfService $pdfService)
    {
        $report = new SocialReport($this->validatedAttributes($request));

        return $pdfService->render($report)->stream($report->downloadFileName());
    }

    /**
     * Email the saved report to the client. Only ever called from the Send button, never on save.
     */
    public function send(SocialReport $socialReport, SocialReportMailer $mailer)
    {
        try {
            $sentTo = $mailer->send($socialReport);
        } catch (Throwable $exception) {
            Log::error('Social media report email send failed', [
                'social_report_id' => $socialReport->id,
                'report_number' => $socialReport->report_number,
                'customer_id' => $socialReport->customer_id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'send' => [$exception->getMessage() === 'Customer email is missing.'
                    ? 'Add a client contact email to the report, or an email to the customer, before sending.'
                    : 'Social media report email could not be sent. Check the mail settings.'],
            ]);
        }

        $socialReport->forceFill(['sent_at' => now(), 'sent_to' => $sentTo])->save();

        return response()->json([
            'message' => "Social media report sent to {$sentTo}.",
            'data' => $socialReport->fresh()->load('customer:id,name,email'),
        ]);
    }

    /**
     * Store a top-post image, cropped to the thumbnail shape used on the PDF.
     */
    public function uploadImage(Request $request, SocialReportImageService $images)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ], [], ['image' => 'image']);

        try {
            $path = $images->store($request->file('image'));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['image' => ['That image could not be read. Try a JPG or PNG.']]);
        }

        return response()->json(['data' => ['path' => $path]], 201);
    }

    public function showImage(string $name)
    {
        $path = 'social-report-images/'.$name;
        abort_unless(preg_match(SocialReport::IMAGE_PATH_PATTERN, $path) === 1 && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(Request $request): array
    {
        $number = ['nullable', 'numeric', 'min:0', 'max:999999999'];
        $percent = ['nullable', 'numeric', 'min:0', 'max:100'];
        $text = ['nullable', 'string', 'max:255'];
        $platform = 'data.platforms.*';

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'report_number' => ['nullable', 'string', 'max:50'],
            'report_month' => ['required', 'date_format:Y-m'],
            'issue_date' => ['required', 'date'],

            'data' => ['required', 'array'],
            'data.contact_name' => $text,
            'data.contact_email' => $text,
            'data.summary' => ['nullable', 'string', 'max:600'],
            'data.channels' => ['required', 'array', 'min:1'],
            'data.channels.*' => ['string', Rule::in(array_keys(SocialReport::PLATFORMS))],

            'data.totals' => ['nullable', 'array'],
            'data.totals.engagements.current' => $number,
            'data.totals.engagements.previous' => $number,
            'data.totals.engagement_rate.current' => $percent,
            'data.totals.engagement_rate.previous' => $percent,
            'data.totals.website_clicks.current' => $number,
            'data.totals.website_clicks.previous' => $number,

            'data.platforms' => ['nullable', 'array'],
            "{$platform}.handle" => ['nullable', 'string', 'max:60'],
            "{$platform}.posts.current" => $number,
            "{$platform}.posts.previous" => $number,
            "{$platform}.metrics" => ['nullable', 'array'],
            "{$platform}.metrics.*.current" => $number,
            "{$platform}.metrics.*.previous" => $number,
            "{$platform}.metrics.engagement_rate.current" => $percent,
            "{$platform}.metrics.engagement_rate.previous" => $percent,
            "{$platform}.avg_duration.current" => ['nullable', 'string', 'regex:/^\d{1,3}:[0-5]\d$/'],
            "{$platform}.avg_duration.previous" => ['nullable', 'string', 'regex:/^\d{1,3}:[0-5]\d$/'],
            "{$platform}.history" => ['nullable', 'array', 'max:5'],
            "{$platform}.history.*" => $number,
            "{$platform}.audience" => ['nullable', 'array', 'max:4'],
            "{$platform}.audience.*" => ['nullable', 'string', 'max:24'],
            "{$platform}.formats" => ['nullable', 'array', 'max:3'],
            "{$platform}.formats.*.label" => ['nullable', 'string', 'max:40'],
            "{$platform}.formats.*.posts" => $number,
            "{$platform}.formats.*.average" => $number,
            "{$platform}.formats.*.engagement" => $percent,
            "{$platform}.top_posts" => ['nullable', 'array', 'max:3'],
            "{$platform}.top_posts.*.title" => ['nullable', 'string', 'max:70'],
            "{$platform}.top_posts.*.image" => ['nullable', 'string', 'regex:'.SocialReport::IMAGE_PATH_PATTERN],
            "{$platform}.top_posts.*.stat" => $number,
            "{$platform}.top_posts.*.metric" => $number,
            "{$platform}.top_posts.*.metric_label" => ['nullable', 'string', 'max:12'],
            "{$platform}.insight" => ['nullable', 'string', 'max:300'],

            'data.prepared_by' => ['nullable', 'array'],
            'data.prepared_by.phone' => $text,
            'data.prepared_by.email' => $text,
        ], [
            'data.channels.required' => 'Tick at least one social media channel.',
            'data.channels.min' => 'Tick at least one social media channel.',
            "{$platform}.avg_duration.current.regex" => 'Enter the average view duration as minutes:seconds, e.g. 3:42.',
            "{$platform}.avg_duration.previous.regex" => 'Enter last month\'s average view duration as minutes:seconds, e.g. 3:18.',
        ], [
            'customer_id' => 'customer',
            'data.summary' => 'this month in brief',
            'data.totals.engagements.current' => 'engagements this month',
            'data.totals.engagement_rate.current' => 'average engagement rate',
            'data.totals.website_clicks.current' => 'clicks to website',
            "{$platform}.handle" => 'account handle',
            "{$platform}.metrics.*.current" => 'this month\'s figure',
            "{$platform}.metrics.*.previous" => 'last month\'s figure',
            "{$platform}.metrics.engagement_rate.current" => 'engagement rate',
            "{$platform}.metrics.engagement_rate.previous" => 'last month\'s engagement rate',
            "{$platform}.history.*" => 'monthly new followers figure',
            "{$platform}.audience.*" => 'audience snapshot value',
            "{$platform}.formats.*.label" => 'content format',
            "{$platform}.formats.*.engagement" => 'format engagement rate',
            "{$platform}.top_posts.*.title" => 'top post title',
            "{$platform}.top_posts.*.image" => 'top post image',
            "{$platform}.top_posts.*.metric_label" => 'top post stat name',
            "{$platform}.insight" => 'insight',
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['report_month'])->startOfMonth();
        $customer = Customer::query()->findOrFail($validated['customer_id']);

        return [
            'customer_id' => $customer->id,
            'report_number' => trim((string) ($validated['report_number'] ?? '')) ?: $this->defaultReportNumber($customer, $month),
            'report_month' => $month->toDateString(),
            'issue_date' => $validated['issue_date'],
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

        return 'SM-'.($initials !== '' ? $initials : 'WS').'-'.$month->format('my');
    }

    /**
     * Keep channels in report order and drop rows the form left blank, so the PDF only
     * shows what was filled in. Unticked channels keep their figures for later months.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanData(array $data): array
    {
        $data['channels'] = array_values(array_intersect(array_keys(SocialReport::PLATFORMS), $data['channels'] ?? []));

        $platforms = [];
        foreach ($data['platforms'] ?? [] as $key => $platform) {
            if (! isset(SocialReport::PLATFORMS[$key]) || ! is_array($platform)) {
                continue;
            }

            $keepRows = fn (string $group, string $requiredField): array => array_values(array_filter(
                $platform[$group] ?? [],
                fn ($row): bool => is_array($row) && trim((string) ($row[$requiredField] ?? '')) !== ''
            ));

            $platform['formats'] = $keepRows('formats', 'label');
            $platform['top_posts'] = $keepRows('top_posts', 'title');
            $platform['history'] = array_map(
                fn ($value) => $value === null || $value === '' ? null : (float) $value,
                array_values($platform['history'] ?? [])
            );
            $platform['audience'] = array_map(fn ($value) => trim((string) $value), array_values($platform['audience'] ?? []));
            $platforms[$key] = $platform;
        }
        $data['platforms'] = $platforms;

        return $data;
    }
}
