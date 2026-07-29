<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FollowUpEnrolment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'sequence_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FollowUpExecution::class, 'enrolment_id');
    }
}
