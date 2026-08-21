<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringCostRate extends Model
{
    protected $fillable = ['recurring_cost_id', 'amount', 'effective_from'];

    protected $casts = ['effective_from' => 'date', 'amount' => 'decimal:2'];

    public function recurringCost(): BelongsTo
    {
        return $this->belongsTo(RecurringCost::class);
    }
}
