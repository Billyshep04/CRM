<?php

namespace App\Enums;

enum RevenueOpportunityType: string
{
    case Hosting = 'hosting';
    case Seo = 'seo';
    case CarePlan = 'care_plan';
    case WebsiteManagement = 'website_management';
    case NewWebsite = 'new_website';
    case Upsell = 'upsell';
    case Retention = 'retention';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Website Hosting', self::Seo => 'SEO Package', self::CarePlan => 'Website Care Plan',
            self::WebsiteManagement => 'Website Management', self::NewWebsite => 'New Website Build',
            self::Upsell => 'Upsell', self::Retention => 'Customer Retention',
        };
    }
}
