<?php

namespace App\Exceptions;

use RuntimeException;

class ProvisioningWait extends RuntimeException
{
    public function __construct(public readonly string $state, string $message, public readonly int $retryMinutes = 10)
    {
        parent::__construct($message);
    }
}
