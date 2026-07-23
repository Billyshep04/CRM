<?php

namespace App\Contracts;

interface LeadDiscoveryProvider
{
    /** @return array{places: list<array<string, mixed>>, next_page_token: ?string} */
    public function search(string $query, string $location, int $pageSize = 20, ?string $pageToken = null): array;
}
