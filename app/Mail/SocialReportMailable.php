<?php

namespace App\Mail;

use App\Models\SocialReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SocialReportMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $emailData
     */
    public function __construct(
        public SocialReport $report,
        public string $subjectLine,
        public array $emailData,
        public string $pdf
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.social-report', $this->emailData)
            ->attachData($this->pdf, $this->report->downloadFileName(), ['mime' => 'application/pdf']);
    }
}
