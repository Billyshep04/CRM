<?php

namespace App\Jobs;

use App\Contracts\LeadScoreRepository;
use App\Contracts\LeadScoringEngine;
use App\Models\Business;
use App\Models\LeadScoringProfile;
use App\Models\WebsiteAudit;
use App\Services\LeadScoring\DefaultLeadScoringProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CalculateLeadScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 120];

    public function __construct(public readonly int $businessId, public readonly int $auditId, public readonly ?int $profileId = null, public readonly ?int $userId = null)
    {
        $this->onQueue('scoring');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('lead-score:'.$this->businessId))->expireAfter(90)];
    }

    public function handle(LeadScoringEngine $engine, LeadScoreRepository $scores, DefaultLeadScoringProfile $defaults): void
    {
        $business = Business::query()->findOrFail($this->businessId);
        $audit = WebsiteAudit::query()->findOrFail($this->auditId);
        $profile = $this->profileId
            ? LeadScoringProfile::query()->where('is_active', true)->findOrFail($this->profileId)
            : $defaults->resolve();
        $scores->store($business, $audit, $profile, $engine->calculate($business, $audit, $profile), $this->userId);
    }
}
