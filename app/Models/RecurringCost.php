<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringCost extends Model
{
    use SoftDeletes;

    protected $fillable = ['description', 'frequency', 'starts_on', 'ends_on', 'active', 'notes', 'receipt_file_id', 'created_by_user_id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'active' => 'boolean'];

    public function rates(): HasMany
    {
        return $this->hasMany(RecurringCostRate::class)->orderBy('effective_from');
    }

    public function receiptFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'receipt_file_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
