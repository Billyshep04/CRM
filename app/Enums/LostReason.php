<?php

namespace App\Enums;

enum LostReason: string
{
    case NoBudget = 'no_budget';
    case ExistingProvider = 'existing_provider';
    case NoNeed = 'no_need';
    case Timing = 'timing';
    case Price = 'price';
    case LostToCompetitor = 'lost_to_competitor';
    case Unresponsive = 'unresponsive';
    case InvalidUnqualified = 'invalid_unqualified';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
