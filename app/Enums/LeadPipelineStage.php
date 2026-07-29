<?php

namespace App\Enums;

enum LeadPipelineStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Contacted = 'contacted';
    case Discovery = 'discovery';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
    case Deferred = 'deferred';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Won, self::Lost, self::Deferred], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
