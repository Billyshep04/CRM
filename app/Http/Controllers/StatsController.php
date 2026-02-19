<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use App\Models\Job;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class StatsController extends Controller
{
    public function revenue(): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $completedJobsTotal = (float) Job::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($startOfMonth, $endOfMonth): void {
                $query
                    ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($fallback) use ($startOfMonth, $endOfMonth): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->sum('cost');

        $activeSubscriptionsTotal = (float) Subscription::query()
            ->where('status', 'active')
            ->sum('monthly_cost');

        $monthlyCostsTotal = 0.0;
        if (Schema::hasTable('costs')) {
            $monthlyCostsTotal = (float) Cost::query()
                ->whereBetween('incurred_on', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->sum('amount');
        }

        $revenueTotal = $completedJobsTotal + $activeSubscriptionsTotal;
        $profitTotal = $revenueTotal - $monthlyCostsTotal;

        return response()->json([
            'completed_jobs_total' => $completedJobsTotal,
            'active_subscriptions_total' => $activeSubscriptionsTotal,
            'costs_total' => $monthlyCostsTotal,
            'profit_total' => $profitTotal,
            'total' => $revenueTotal,
        ]);
    }
}
