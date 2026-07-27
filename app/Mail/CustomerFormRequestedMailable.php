<?php

namespace App\Mail;

use App\Models\CustomerFormRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerFormRequestedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerFormRequest $formRequest,
        public string $portalUrl
    ) {
        $this->formRequest->loadMissing('customer');
    }

    public function build(): self
    {
        return $this->subject("New form to complete: {$this->formRequest->template_name}")
            ->view('emails.customer-form-requested', [
                'formRequest' => $this->formRequest,
                'customer' => $this->formRequest->customer,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
