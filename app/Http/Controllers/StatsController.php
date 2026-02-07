<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function revenue(): JsonResponse
    {
        $completedJobsTotal = (float) Job::query()
            ->where('status', 'completed')
            ->sum('cost');

        $activeSubscriptionsTotal = (float) Subscription::query()
            ->where('status', 'active')
            ->sum('monthly_cost');

        return response()->json([
            'completed_jobs_total' => $completedJobsTotal,
            'active_subscriptions_total' => $activeSubscriptionsTotal,
            'total' => $completedJobsTotal + $activeSubscriptionsTotal,
        ]);
    }
}
