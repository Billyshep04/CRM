<?php

namespace App\Models;

use App\Enums\CrmActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmActivity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => CrmActivityType::class, 'occurred_at' => 'datetime', 'next_action_at' => 'datetime', 'metadata' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
