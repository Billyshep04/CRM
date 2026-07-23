<?php

namespace App\Services\RevenueOpportunities;

use App\Enums\RevenueOpportunityType;
use App\Models\Customer;
use App\Models\RevenueOpportunity;
use Illuminate\Support\Str;

class OpportunityRecommendationService
{
    /** @return array{created: int, existing: int} */
    public function recommend(?int $userId = null): array
    {
        $created = 0;
        $existing = 0;

        Customer::query()->with(['websites', 'subscriptions' => fn ($query) => $query->where('status', 'active')])->each(function (Customer $customer) use ($userId, &$created, &$existing): void {
            foreach ($customer->websites as $website) {
                foreach ($this->missingServices($customer) as $type) {
                    $fingerprint = hash('sha256', implode(':', [$customer->id, $website->id, $type->value]));
                    if (RevenueOpportunity::withTrashed()->where('fingerprint', $fingerprint)->exists()) {
                        $existing++;

                        continue;
                    }

                    $values = config('agency-os.opportunity_values.'.$type->value, ['project' => 0, 'monthly' => 0]);
                    RevenueOpportunity::query()->create([
                        'public_id' => (string) Str::ulid(), 'customer_id' => $customer->id, 'website_id' => $website->id,
                        'owner_user_id' => $userId, 'created_by_user_id' => $userId, 'type' => $type,
                        'title' => $type->label().' opportunity', 'source' => 'automatic', 'fingerprint' => $fingerprint,
                        'confidence' => 70, 'estimated_project_value' => $values['project'],
                        'estimated_monthly_revenue' => $values['monthly'],
                        'recommendation' => $this->recommendation($type, $customer->name),
                    ]);
                    $created++;
                }
            }
        });

        return compact('created', 'existing');
    }

    /** @return list<RevenueOpportunityType> */
    private function missingServices(Customer $customer): array
    {
        $descriptions = $customer->subscriptions->pluck('description')->map(fn ($value) => strtolower((string) $value))->implode(' ');
        $services = [];
        if (! str_contains($descriptions, 'hosting')) {
            $services[] = RevenueOpportunityType::Hosting;
        }
        if (! str_contains($descriptions, 'seo')) {
            $services[] = RevenueOpportunityType::Seo;
        }
        if (! str_contains($descriptions, 'care') && ! str_contains($descriptions, 'maintenance')) {
            $services[] = RevenueOpportunityType::CarePlan;
        }
        if (! str_contains($descriptions, 'management')) {
            $services[] = RevenueOpportunityType::WebsiteManagement;
        }

        return $services;
    }

    private function recommendation(RevenueOpportunityType $type, string $customer): string
    {
        return match ($type) {
            RevenueOpportunityType::Hosting => "Review whether {$customer}'s website hosting can be consolidated into an agency-managed recurring plan.",
            RevenueOpportunityType::Seo => "Offer {$customer} an ongoing SEO package with measurable monthly reporting.",
            RevenueOpportunityType::CarePlan => "Protect {$customer}'s website with updates, backups, security checks, and support.",
            RevenueOpportunityType::WebsiteManagement => "Offer proactive content, technical, and conversion management for {$customer}'s website.",
            default => "Review the next best service for {$customer}.",
        };
    }
}
