<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailSubscriber extends Model
{
    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_BOUNCED = 'bounced';

    /**
     * Where consent to email this person came from (UK PECR / GDPR record).
     *
     * @var array<string, string>
     */
    public const CONSENT_SOURCES = [
        'manual' => 'Added manually',
        'existing_client' => 'Existing client',
        'website_form' => 'Website form',
        'csv_import' => 'CSV import',
        'event' => 'Event or in person',
        'other' => 'Other',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'status',
        'consent_source',
        'consent_note',
        'unsubscribed_at',
        'bounced_at',
        'created_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unsubscribed_at' => 'datetime',
        'bounced_at' => 'datetime',
    ];

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(EmailList::class, 'email_list_subscriber')
            ->withPivot('added_via')
            ->withTimestamps();
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = self::normaliseEmail((string) $value);
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
