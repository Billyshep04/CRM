<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailCampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email_campaign_id',
        'email_subscriber_id',
        'email',
        'first_name',
        'last_name',
        'token_hash',
        'status',
        'error',
        'attempts',
        'provider_message_id',
        'sent_at',
        'unsubscribed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(EmailSubscriber::class, 'email_subscriber_id');
    }

    /**
     * Creates a fresh unguessable token for this recipient's links. Only the hash is kept,
     * so the raw token exists only inside the email that was sent.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['token_hash' => self::hashToken($token)])->save();

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        return self::where('token_hash', self::hashToken($token))->first();
    }
}
