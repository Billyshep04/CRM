<?php

namespace App\Models;

use App\Enums\LeadScoreFactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScoringWeight extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['factor' => LeadScoreFactor::class, 'weight' => 'float', 'is_enabled' => 'boolean'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LeadScoringProfile::class, 'lead_scoring_profile_id');
    }
}
