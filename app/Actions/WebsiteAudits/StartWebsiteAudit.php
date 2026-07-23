<?php

namespace App\Actions\WebsiteAudits;

use App\Contracts\WebsiteAuditRepository;
use App\Jobs\AnalyzeWebsite;
use App\Models\WebsiteAudit;

class StartWebsiteAudit
{
    public function __construct(private readonly WebsiteAuditRepository $audits) {}

    public function execute(string $url, ?int $websiteId, ?int $businessId, ?int $userId): WebsiteAudit
    {
        $audit = $this->audits->createPending($url, $websiteId, $businessId, $userId);

        AnalyzeWebsite::dispatch($audit->id)->afterCommit();

        return $audit;
    }
}
