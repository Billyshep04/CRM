<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Services\EmailMarketing\CampaignSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Sends one minute's worth of a campaign, then queues the next batch. Runs on the default
 * queue, which the existing cron worker already processes.
 */
class SendEmailCampaignBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(CampaignSender $sender): void
    {
        $campaign = EmailCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== EmailCampaign::STATUS_SENDING) {
            return;
        }

        // Only one batch per campaign at a time.
        $lock = Cache::lock("email-campaign-send:{$campaign->id}", 660);
        if (!$lock->get()) {
            return;
        }

        $started = microtime(true);
        try {
            $more = $sender->sendBatch($campaign);
        } finally {
            $lock->release();
        }

        if ($more) {
            $wait = max(1, 60 - (int) (microtime(true) - $started));
            self::dispatch($campaign->id)->delay(now()->addSeconds($wait));
        }
    }
}
