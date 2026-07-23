<?php

namespace App\Models;

use App\Enums\RevenueOpportunityStatus;
use App\Enums\RevenueOpportunityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueOpportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => RevenueOpportunityType::class, 'status' => RevenueOpportunityStatus::class,
            'estimated_project_value' => 'decimal:2', 'estimated_monthly_revenue' => 'decimal:2',
            'renewal_due_at' => 'date', 'next_action_at' => 'datetime', 'won_at' => 'datetime', 'lost_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function convertedSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'converted_subscription_id');
    }

    public function convertedJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'converted_job_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }
}
