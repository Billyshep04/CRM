<?php

namespace App\Models;

use App\Enums\AuditFindingSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['severity' => AuditFindingSeverity::class, 'evidence' => 'array'];
    }

    public function websiteAudit(): BelongsTo
    {
        return $this->belongsTo(WebsiteAudit::class);
    }
}
