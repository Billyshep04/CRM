<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by_user_id',
        'report_number',
        'report_month',
        'issue_date',
        'website_domain',
        'data',
    ];

    protected $casts = [
        'report_month' => 'date',
        'issue_date' => 'date',
        'data' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function downloadFileName(): string
    {
        return 'SEO-Report-'.$this->report_number.'.pdf';
    }
}
