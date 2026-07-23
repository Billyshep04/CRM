<?php

namespace App\Contracts;

use App\Models\WebsiteAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WebsiteAuditRepository
{
    public function createPending(string $url, ?int $websiteId, ?int $businessId, ?int $userId): WebsiteAudit;

    /** @param array{website_id?: int|null, status?: string|null, per_page?: int|null} $filters */
    public function paginate(array $filters): LengthAwarePaginator;
}
