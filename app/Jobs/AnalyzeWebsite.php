<?php

namespace App\Jobs;

use App\Contracts\WebsiteAnalyzer;
use App\Enums\WebsiteAuditStatus;
use App\Models\WebsiteAudit;
use App\Services\WebsiteAnalysis\AuditResultPersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzeWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $auditId)
    {
        $this->onQueue((string) config('website-audits.queue', 'audit'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('website-audit:'.$this->auditId))->expireAfter($this->timeout + 30)];
    }

    public function handle(WebsiteAnalyzer $analyzer, AuditResultPersister $persister): void
    {
        $audit = WebsiteAudit::query()->findOrFail($this->auditId);
        if ($audit->status === WebsiteAuditStatus::Completed) {
            return;
        }

        $audit->update([
            'status' => WebsiteAuditStatus::Running,
            'started_at' => $audit->started_at ?? now(),
            'failed_at' => null,
            'failure_code' => null,
            'failure_message' => null,
        ]);

        $persister->persist($audit, $analyzer->analyze($audit->requested_url));
    }

    public function failed(?Throwable $exception): void
    {
        WebsiteAudit::query()->whereKey($this->auditId)->update([
            'status' => WebsiteAuditStatus::Failed->value,
            'failed_at' => now(),
            'failure_code' => $exception ? class_basename($exception) : 'unknown',
            'failure_message' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : 'The audit job failed.',
        ]);
    }
}
