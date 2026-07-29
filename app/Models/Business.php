<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'domain_registered_at' => 'date',
            'missing_features' => 'array',
            'metadata' => 'array',
            'lead_score' => 'decimal:2',
            'google_rating' => 'decimal:2',
            'design_quality_score' => 'decimal:2',
            'professionalism_score' => 'decimal:2',
            'lead_scored_at' => 'datetime',
            'discovered_at' => 'datetime',
            'last_discovered_at' => 'datetime',
            'contacted_at' => 'datetime',
            'next_action_at' => 'datetime', 'last_activity_at' => 'datetime', 'expected_close_date' => 'date',
            'estimated_project_value' => 'decimal:2', 'won_at' => 'datetime', 'lost_at' => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by_user_id');
    }

    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(LeadDiscoveryRun::class, 'lead_discovery_run_id');
    }

    public function websiteAudits(): HasMany
    {
        return $this->hasMany(WebsiteAudit::class);
    }

    public function leadScores(): HasMany
    {
        return $this->hasMany(LeadScore::class);
    }

    public function currentLeadScore(): HasOne
    {
        return $this->hasOne(LeadScore::class)->where('is_current', true)->latestOfMany('calculated_at');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject')->latest('occurred_at');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(PipelineStageTransition::class);
    }
}
