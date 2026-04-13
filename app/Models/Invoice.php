<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by_user_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'total',
        'pdf_file_id',
        'sent_at',
        'paid_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        $normalized = strtolower(trim((string) $status));

        if ($normalized === '' || $normalized === 'all') {
            return $query;
        }

        if ($normalized === 'overdue') {
            return $query
                ->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', today());
        }

        if ($normalized === 'sent') {
            return $query
                ->where('status', 'sent')
                ->whereDate('due_date', '>=', today());
        }

        return $query->where('status', $normalized);
    }

    public function effectiveStatus(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->lt(today())) {
            return 'overdue';
        }

        return $this->status;
    }
}
