<?php

namespace App\Services\LeadScoring;

use App\Enums\LeadScoreFactor;
use App\Models\LeadScoringProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefaultLeadScoringProfile
{
    public function resolve(): LeadScoringProfile
    {
        return DB::transaction(function (): LeadScoringProfile {
            $profile = LeadScoringProfile::query()->where('is_default', true)->where('is_active', true)->lockForUpdate()->first();
            if ($profile) {
                return $profile;
            }

            $version = ((int) LeadScoringProfile::withTrashed()->where('name', 'LeadForge Default')->max('version')) + 1;
            $profile = LeadScoringProfile::query()->create([
                'public_id' => (string) Str::ulid(), 'name' => 'LeadForge Default', 'version' => $version,
                'is_default' => true, 'is_active' => true,
                'description' => 'Prioritises established, well-reviewed businesses whose websites have strong improvement potential.',
            ]);
            $profile->weights()->createMany(collect(LeadScoreFactor::defaultWeights())->map(fn (float $weight, string $factor) => ['factor' => $factor, 'weight' => $weight, 'is_enabled' => true])->values()->all());

            return $profile->load('weights');
        });
    }
}
