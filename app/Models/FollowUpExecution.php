<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpExecution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(FollowUpEnrolment::class, 'enrolment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequenceStep::class, 'step_id');
    }
}
