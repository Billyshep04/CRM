<?php

namespace App\Mail;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProposalMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Proposal $proposal)
    {
        $this->proposal->loadMissing(['customer', 'job', 'lineItems', 'pdfFile']);
    }

    public function build(): self
    {
        $mail = $this->subject("Proposal {$this->proposal->proposal_number} (v{$this->proposal->version})")
            ->view('emails.proposal', [
                'proposal' => $this->proposal,
                'customer' => $this->proposal->customer,
                'job' => $this->proposal->job,
            ]);

        if (
            $this->proposal->pdfFile
            && is_string($this->proposal->pdfFile->disk)
            && is_string($this->proposal->pdfFile->path)
            && $this->proposal->pdfFile->disk !== ''
            && $this->proposal->pdfFile->path !== ''
        ) {
            $mail->attachFromStorageDisk(
                $this->proposal->pdfFile->disk,
                $this->proposal->pdfFile->path,
                "Proposal-{$this->proposal->proposal_number}-v{$this->proposal->version}.pdf",
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
