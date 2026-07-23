<?php

namespace App\Console\Commands;

use App\Jobs\SendOpportunityFollowUpReminder;
use App\Models\CrmTask;
use Illuminate\Console\Command;

class SendOpportunityFollowUpReminders extends Command
{
    protected $signature = 'opportunities:send-follow-up-reminders';

    protected $description = 'Queue administrator reminders for due revenue-opportunity follow-ups.';

    public function handle(): int
    {
        $count = 0;
        CrmTask::query()->whereNotNull('revenue_opportunity_id')->whereNull('reminder_sent_at')
            ->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_date', '<=', today())
            ->select('id')->orderBy('id')->chunkById(100, function ($tasks) use (&$count): void {
                foreach ($tasks as $task) {
                    SendOpportunityFollowUpReminder::dispatch($task->id);
                    $count++;
                }
            });
        $this->info("Queued {$count} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
