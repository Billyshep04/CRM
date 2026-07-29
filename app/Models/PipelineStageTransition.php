<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStageTransition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
