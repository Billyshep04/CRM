<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionMonth extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'month_start',
        'subscription_status',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'month_start' => 'date',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
