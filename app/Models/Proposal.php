<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'job_id',
        'created_by_user_id',
        'parent_proposal_id',
        'proposal_number',
        'version',
        'title',
        'issue_date',
        'expiry_date',
        'status',
        'notes',
        'terms',
        'subtotal',
        'total',
        'pdf_file_id',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'locked_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_proposal_id');
    }

    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ProposalLineItem::class);
    }

    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        $normalized = strtolower(trim((string) $status));

        if ($normalized === '' || $normalized === 'all') {
            return $query;
        }

        if ($normalized === 'expired') {
            return $query
                ->whereNotIn('status', ['accepted', 'rejected'])
                ->whereDate('expiry_date', '<', today());
        }

        return $query->where('status', $normalized);
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['accepted', 'rejected'], true)) {
            return $this->status;
        }

        if ($this->expiry_date && $this->expiry_date->lt(today())) {
            return 'expired';
        }

        return $this->status;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || in_array($this->status, ['accepted', 'rejected'], true);
    }
}
