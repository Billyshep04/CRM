<?php

namespace App\Enums;

enum WebsiteAuditStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
