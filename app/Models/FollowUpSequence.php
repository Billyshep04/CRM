<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUpSequence extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FollowUpSequenceStep::class, 'sequence_id')->orderBy('position');
    }
}
