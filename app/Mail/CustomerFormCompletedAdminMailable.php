<?php

namespace App\Mail;

use App\Models\CustomerFormRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerFormCompletedAdminMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerFormRequest $formRequest,
        public string $crmUrl
    ) {
        $this->formRequest->loadMissing('customer');
    }

    public function build(): self
    {
        return $this->subject("Customer form completed: {$this->formRequest->template_name}")
            ->view('emails.customer-form-completed-admin', [
                'formRequest' => $this->formRequest,
                'customer' => $this->formRequest->customer,
                'crmUrl' => $this->crmUrl,
            ]);
    }
}
