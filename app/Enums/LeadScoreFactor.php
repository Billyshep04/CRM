<?php

namespace App\Enums;

enum LeadScoreFactor: string
{
    case WebsiteAge = 'website_age';
    case Performance = 'performance';
    case Seo = 'seo';
    case Accessibility = 'accessibility';
    case Security = 'security';
    case GoogleRating = 'google_rating';
    case GoogleReviewCount = 'google_review_count';
    case WebsiteDesign = 'website_design';
    case MissingFeatures = 'missing_features';
    case BrokenLinks = 'broken_links';
    case MobileFriendliness = 'mobile_friendliness';
    case Https = 'https';
    case Analytics = 'analytics';
    case Schema = 'schema';
    case Professionalism = 'professionalism';

    /** @return array<string, float> */
    public static function defaultWeights(): array
    {
        return [
            self::WebsiteAge->value => 5,
            self::Performance->value => 8,
            self::Seo->value => 10,
            self::Accessibility->value => 5,
            self::Security->value => 6,
            self::GoogleRating->value => 14,
            self::GoogleReviewCount->value => 12,
            self::WebsiteDesign->value => 10,
            self::MissingFeatures->value => 8,
            self::BrokenLinks->value => 5,
            self::MobileFriendliness->value => 5,
            self::Https->value => 3,
            self::Analytics->value => 2,
            self::Schema->value => 2,
            self::Professionalism->value => 5,
        ];
    }
}
