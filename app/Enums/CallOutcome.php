<?php

namespace App\Enums;

enum CallOutcome: string
{
    case NoAnswer = 'no_answer';
    case Voicemail = 'voicemail';
    case Conversation = 'conversation';
    case Interested = 'interested';
    case CallBack = 'call_back';
    case SendInformation = 'send_information';
    case MeetingBooked = 'meeting_booked';
    case NotInterested = 'not_interested';
    case ExistingProvider = 'existing_provider';
    case NoBudget = 'no_budget';
    case BadTiming = 'bad_timing';
    case InvalidNumber = 'invalid_number';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresNextAction(): bool
    {
        return in_array($this, [self::NoAnswer, self::Voicemail, self::Interested, self::CallBack, self::SendInformation, self::MeetingBooked, self::BadTiming], true);
    }
}
