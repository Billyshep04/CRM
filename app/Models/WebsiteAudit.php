<?php

namespace App\Models;

use App\Enums\WebsiteAuditStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WebsiteAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'website_id', 'business_id', 'requested_by_user_id', 'version', 'status',
        'requested_url', 'final_url', 'http_status', 'http_version', 'overall_score',
        'seo_score', 'performance_score', 'accessibility_score', 'security_score',
        'redirect_chain', 'structured_results', 'started_at', 'completed_at',
        'failed_at', 'failure_code', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebsiteAuditStatus::class,
            'redirect_chain' => 'array',
            'structured_results' => 'array',
            'overall_score' => 'decimal:2',
            'seo_score' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'accessibility_score' => 'decimal:2',
            'security_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function seoAudit(): HasOne
    {
        return $this->hasOne(SeoAudit::class);
    }

    public function performanceAudit(): HasOne
    {
        return $this->hasOne(PerformanceAudit::class);
    }

    public function accessibilityAudit(): HasOne
    {
        return $this->hasOne(AccessibilityAudit::class);
    }

    public function securityAudit(): HasOne
    {
        return $this->hasOne(SecurityAudit::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }
}
