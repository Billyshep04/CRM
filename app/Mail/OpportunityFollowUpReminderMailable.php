<?php

namespace App\Mail;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OpportunityFollowUpReminderMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CrmTask $task)
    {
        $this->task->loadMissing(['assignedTo', 'revenueOpportunity.customer']);
    }

    public function build(): self
    {
        return $this->subject('Follow-up reminder: '.$this->task->revenueOpportunity?->title)
            ->view('emails.opportunity-follow-up-reminder');
    }
}
