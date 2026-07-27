<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFormRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'sent_by_user_id',
        'template_slug',
        'template_name',
        'form_schema',
        'answers',
        'status',
        'sent_at',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'form_schema' => 'array',
        'answers' => 'array',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
