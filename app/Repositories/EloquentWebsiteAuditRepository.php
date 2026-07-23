<?php

namespace App\Repositories;

use App\Contracts\WebsiteAuditRepository;
use App\Enums\WebsiteAuditStatus;
use App\Models\WebsiteAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentWebsiteAuditRepository implements WebsiteAuditRepository
{
    public function createPending(string $url, ?int $websiteId, ?int $businessId, ?int $userId): WebsiteAudit
    {
        return DB::transaction(function () use ($url, $websiteId, $businessId, $userId): WebsiteAudit {
            $versionQuery = WebsiteAudit::query();
            if ($businessId) {
                $versionQuery->where('business_id', $businessId);
            } elseif ($websiteId) {
                $versionQuery->where('website_id', $websiteId);
            } else {
                $versionQuery = null;
            }
            $version = $versionQuery ? ((int) $versionQuery->lockForUpdate()->max('version')) + 1 : 1;

            return WebsiteAudit::query()->create([
                'public_id' => (string) Str::ulid(),
                'website_id' => $websiteId,
                'business_id' => $businessId,
                'requested_by_user_id' => $userId,
                'version' => $version,
                'status' => WebsiteAuditStatus::Pending,
                'requested_url' => $url,
            ]);
        });
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return WebsiteAudit::query()
            ->when($filters['website_id'] ?? null, fn ($query, $id) => $query->where('website_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }
}
