<?php

namespace App\Mail;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskCompletedAdminMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CrmTask $task)
    {
        $this->task->loadMissing(['assignedTo', 'job.customer']);
    }

    public function build(): self
    {
        return $this
            ->subject("Task completed: {$this->task->title}")
            ->view('emails.task-completed-admin', [
                'task' => $this->task,
                'staff' => $this->task->assignedTo,
                'job' => $this->task->job,
                'customer' => $this->task->job?->customer,
            ]);
    }
}
