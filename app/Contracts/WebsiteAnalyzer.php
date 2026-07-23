<?php

namespace App\Contracts;

interface WebsiteAnalyzer
{
    /** @return array<string, mixed> */
    public function analyze(string $url): array;
}
