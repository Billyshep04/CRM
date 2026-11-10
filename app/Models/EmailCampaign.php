<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subject',
        'preview_text',
        'from_name',
        'from_email',
        'reply_to',
        'html_body',
        'status',
        'last_error',
        'scheduled_at',
        'started_at',
        'sent_at',
        'recipients_count',
        'sent_count',
        'failed_count',
        'unsubscribed_count',
        'created_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(EmailList::class, 'email_campaign_list')->withTimestamps();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EmailSendAttempt::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }
}
