<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialReport extends Model
{
    /**
     * Uploaded top-post images live on the local disk under this folder.
     */
    public const IMAGE_PATH_PATTERN = '/^social-report-images\/[A-Za-z0-9]{32}\.jpg$/';

    /**
     * What each channel's page is called and labelled, in report order. The CRM form
     * keeps a matching copy in resources/js/app.js.
     *
     * Card kinds: count (followers), compact (reach-style totals), rate (engagement %),
     * percent (plain counts compared by %), hours (watch time) and duration (m:ss).
     *
     * @var array<string, array<string, mixed>>
     */
    public const PLATFORMS = [
        'instagram' => [
            'name' => 'Instagram',
            'cards' => [['followers', 'Followers', 'count'], ['reach', 'Reach', 'compact'], ['engagement_rate', 'Engagement rate', 'rate'], ['highlight', 'Profile visits', 'percent']],
            'history' => 'New followers',
            'audience' => ['Top location', 'Main age group', 'Best day to post', 'Saves this month'],
            'posts_column' => 'Posts',
            'average_column' => 'Avg. reach',
            'post_metric' => 'reach',
            'posts_word' => 'posts',
            'top_label' => 'Top posts this month',
        ],
        'facebook' => [
            'name' => 'Facebook',
            'cards' => [['followers', 'Page followers', 'count'], ['reach', 'Reach', 'compact'], ['engagement_rate', 'Engagement rate', 'rate'], ['highlight', 'Link clicks', 'percent']],
            'history' => 'New followers',
            'audience' => ['Top location', 'Main age group', 'Best day to post', 'New recommendations'],
            'posts_column' => 'Posts',
            'average_column' => 'Avg. reach',
            'post_metric' => 'reach',
            'posts_word' => 'posts',
            'top_label' => 'Top posts this month',
        ],
        'threads' => [
            'name' => 'Threads',
            'cards' => [['followers', 'Followers', 'count'], ['reach', 'Views', 'compact'], ['engagement_rate', 'Engagement rate', 'rate'], ['highlight', 'Replies', 'percent']],
            'history' => 'New followers',
            'audience' => ['Top location', 'Main age group', 'Best day to post', 'Reposts & quotes'],
            'posts_column' => 'Posts',
            'average_column' => 'Avg. views',
            'post_metric' => 'views',
            'posts_word' => 'posts',
            'top_label' => 'Top posts this month',
        ],
        'linkedin' => [
            'name' => 'LinkedIn',
            'cards' => [['followers', 'Followers', 'count'], ['reach', 'Impressions', 'compact'], ['engagement_rate', 'Engagement rate', 'rate'], ['highlight', 'Page views', 'percent']],
            'history' => 'New followers',
            'audience' => ['Top location', 'Top industry', 'Best day to post', 'Clicks to website'],
            'posts_column' => 'Posts',
            'average_column' => 'Avg. impressions',
            'post_metric' => 'impr.',
            'posts_word' => 'posts',
            'top_label' => 'Top posts this month',
        ],
        'x' => [
            'name' => 'X',
            'cards' => [['followers', 'Followers', 'count'], ['reach', 'Impressions', 'compact'], ['engagement_rate', 'Engagement rate', 'rate'], ['highlight', 'Profile visits', 'percent']],
            'history' => 'New followers',
            'audience' => ['Top location', 'Main age group', 'Best day to post', 'Mentions'],
            'posts_column' => 'Posts',
            'average_column' => 'Avg. impressions',
            'post_metric' => 'impr.',
            'posts_word' => 'posts',
            'top_label' => 'Top posts this month',
        ],
        'youtube' => [
            'name' => 'YouTube',
            'cards' => [['followers', 'Subscribers', 'count'], ['reach', 'Views', 'compact'], ['watch_time', 'Watch time', 'hours'], ['avg_duration', 'Avg. view duration', 'duration']],
            'history' => 'New subscribers',
            'audience' => ['Top location', 'Main age group', 'Top traffic source', 'Likes this month'],
            'posts_column' => 'Published',
            'average_column' => 'Avg. views',
            'post_metric' => 'views',
            'posts_word' => 'videos',
            'top_label' => 'Top videos this month',
        ],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'created_by_user_id',
        'report_number',
        'report_month',
        'issue_date',
        'data',
        'sent_at',
        'sent_to',
    ];

    protected $casts = [
        'report_month' => 'date',
        'issue_date' => 'date',
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The channels ticked on the form, in report order.
     *
     * @return list<string>
     */
    public function channels(): array
    {
        $selected = $this->data['channels'] ?? [];

        return array_values(array_filter(array_keys(self::PLATFORMS), fn (string $key): bool => in_array($key, $selected, true)));
    }

    public function downloadFileName(): string
    {
        return 'Social-Media-Report-'.$this->report_number.'.pdf';
    }

    /**
     * The client contact on the report, falling back to the customer's own email.
     */
    public function recipientEmail(): string
    {
        $contact = trim((string) ($this->data['contact_email'] ?? ''));

        return $contact !== '' ? $contact : trim((string) $this->customer?->email);
    }
}
