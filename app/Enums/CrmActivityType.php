<?php

namespace App\Enums;

enum CrmActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';
    case StatusChange = 'status_change';
    case ProposalSent = 'proposal_sent';
    case FollowUp = 'follow_up';
    case Visit = 'visit';
    case System = 'system_event';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
