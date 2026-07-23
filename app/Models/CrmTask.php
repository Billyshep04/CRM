<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmTask extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tasks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assigned_to_user_id',
        'created_by_user_id',
        'job_id',
        'revenue_opportunity_id',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'hours',
        'minutes',
        'staff_notes',
        'completed_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'hours' => 'integer',
        'minutes' => 'integer',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function revenueOpportunity(): BelongsTo
    {
        return $this->belongsTo(RevenueOpportunity::class);
    }

    public function scopeCompletedBetween(Builder $query, mixed $start, mixed $end): Builder
    {
        return $query
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end]);
    }

    public function totalMinutes(): int
    {
        return ((int) $this->hours * 60) + (int) $this->minutes;
    }
}
