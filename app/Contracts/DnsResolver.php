<?php

namespace App\Contracts;

interface DnsResolver
{
    /** @return list<string> */
    public function aRecords(string $host): array;

    /** @return list<string> */
    public function cnameRecords(string $host): array;

    /** @return list<string> */
    public function nameservers(string $host): array;
}
