<?php

namespace App\Mail;

use App\Models\SeoReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeoReportMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $emailData
     */
    public function __construct(
        public SeoReport $report,
        public string $subjectLine,
        public array $emailData,
        public string $pdf
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.seo-report', $this->emailData)
            ->attachData($this->pdf, $this->report->downloadFileName(), ['mime' => 'application/pdf']);
    }
}
