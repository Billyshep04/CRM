<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScore extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'confidence' => 'decimal:2', 'breakdown' => 'array', 'input_snapshot' => 'array', 'is_current' => 'boolean', 'calculated_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function websiteAudit(): BelongsTo
    {
        return $this->belongsTo(WebsiteAudit::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LeadScoringProfile::class, 'lead_scoring_profile_id');
    }
}
