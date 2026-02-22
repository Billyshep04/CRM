<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cost extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'description',
        'amount',
        'incurred_on',
        'is_recurring',
        'recurring_frequency',
        'notes',
        'receipt_file_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'incurred_on' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function receiptFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'receipt_file_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
