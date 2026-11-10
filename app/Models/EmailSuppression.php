<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmailSuppression extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'reason',
        'source',
        'note',
        'created_by_user_id',
        'lifted_at',
        'lifted_by_user_id',
        'lift_note',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'lifted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at');
    }
}
