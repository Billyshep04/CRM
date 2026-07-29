<?php

namespace App\Console\Commands;

use App\Services\FollowUps\ProcessDueFollowUps;
use Illuminate\Console\Command;

class ProcessFollowUpSequences extends Command
{
    protected $signature = 'follow-ups:process';

    protected $description = 'Create due, idempotent follow-up actions';

    public function handle(ProcessDueFollowUps $service): int
    {
        $result = $service->run();
        $this->info("Processed {$result['done']}; failed {$result['failed']}.");

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
