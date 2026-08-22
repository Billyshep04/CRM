<?php

namespace App\Contracts;

interface SslInspector
{
    /** @return array{valid:bool,hostname_match:bool,issuer:?string,expires_at:?string,error:?string} */
    public function inspect(string $host): array;
}
