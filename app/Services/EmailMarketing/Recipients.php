<?php

namespace App\Services\EmailMarketing;

use App\Models\EmailSubscriber;
use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a campaign would reach: subscribed people on any of the chosen lists, once each,
 * never anyone on the suppression list.
 */
class Recipients
{
    /**
     * @param  list<int>  $listIds
     */
    public static function query(array $listIds): Builder
    {
        return EmailSubscriber::query()
            ->where('status', EmailSubscriber::STATUS_SUBSCRIBED)
            ->whereIn('id', fn ($q) => $q->select('email_subscriber_id')->from('email_list_subscriber')->whereIn('email_list_id', $listIds ?: [0]))
            ->whereNotIn('email', EmailSuppression::active()->select('email'));
    }

    /**
     * @param  list<int>  $listIds
     */
    public static function count(array $listIds): int
    {
        return $listIds === [] ? 0 : self::query($listIds)->count();
    }
}
