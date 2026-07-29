<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpSequenceStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
