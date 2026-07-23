<?php

namespace App\Enums;

enum RevenueOpportunityStatus: string
{
    case Identified = 'identified';
    case Qualified = 'qualified';
    case Proposed = 'proposed';
    case Won = 'won';
    case Lost = 'lost';
    case Deferred = 'deferred';
}
