<?php

namespace Database\Seeders;

use App\Services\LeadScoring\DefaultLeadScoringProfile;
use Illuminate\Database\Seeder;

class LeadScoringProfileSeeder extends Seeder
{
    public function run(): void
    {
        app(DefaultLeadScoringProfile::class)->resolve();
    }
}
