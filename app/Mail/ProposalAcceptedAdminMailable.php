<?php

namespace App\Mail;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProposalAcceptedAdminMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Proposal $proposal)
    {
        $this->proposal->loadMissing(['customer', 'job', 'lineItems']);
    }

    public function build(): self
    {
        return $this->subject("Proposal accepted: {$this->proposal->proposal_number} (v{$this->proposal->version})")
            ->view('emails.proposal-accepted-admin', [
                'proposal' => $this->proposal,
                'customer' => $this->proposal->customer,
                'job' => $this->proposal->job,
            ]);
    }
}
