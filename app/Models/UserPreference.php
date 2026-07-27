<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'theme',
        'monthly_finance_boxes',
        'dashboard_tiles',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'monthly_finance_boxes' => 'array',
        'dashboard_tiles' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
