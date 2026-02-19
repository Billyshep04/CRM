<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use App\Models\Job;
use App\Models\Subscription;
use App\Models\SubscriptionMonth;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function weeklyProfit(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Invalid year.'], 422);
        }

        $startOfYear = Carbon::create($year, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $endOfYear = $startOfYear->copy()->endOfYear()->endOfDay();
        $weeks = $this->buildWeeklyBuckets($startOfYear, $endOfYear);

        $jobs = Job::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($startOfYear, $endOfYear): void {
                $query
                    ->whereBetween('completed_at', [$startOfYear, $endOfYear])
                    ->orWhere(function ($fallback) use ($startOfYear, $endOfYear): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$startOfYear, $endOfYear]);
                    });
            })
            ->get(['cost', 'completed_at', 'created_at']);

        foreach ($jobs as $job) {
            $jobDate = $job->completed_at ?? $job->created_at;
            if (!$jobDate) {
                continue;
            }

            $bucketKey = $this->resolveWeekBucketKey($startOfYear, $endOfYear, Carbon::parse($jobDate));
            if (!$bucketKey) {
                continue;
            }

            $weeks[$bucketKey]['revenue'] += (float) $job->cost;
        }

        if (Schema::hasTable('subscription_months')) {
            $paidMonths = SubscriptionMonth::query()
                ->with('subscription:id,monthly_cost')
                ->where('payment_status', 'paid')
                ->whereBetween('month_start', [$startOfYear->toDateString(), $endOfYear->toDateString()])
                ->get(['subscription_id', 'month_start']);

            foreach ($paidMonths as $paidMonth) {
                $bucketKey = $this->resolveWeekBucketKey(
                    $startOfYear,
                    $endOfYear,
                    Carbon::parse($paidMonth->month_start)
                );
                if (!$bucketKey) {
                    continue;
                }

                $weeks[$bucketKey]['revenue'] += (float) ($paidMonth->subscription?->monthly_cost ?? 0);
            }
        }

        if (Schema::hasTable('costs')) {
            $costs = Cost::query()
                ->whereBetween('incurred_on', [$startOfYear->toDateString(), $endOfYear->toDateString()])
                ->get(['amount', 'incurred_on']);

            foreach ($costs as $cost) {
                if (!$cost->incurred_on) {
                    continue;
                }

                $bucketKey = $this->resolveWeekBucketKey(
                    $startOfYear,
                    $endOfYear,
                    Carbon::parse($cost->incurred_on)
                );
                if (!$bucketKey) {
                    continue;
                }

                $weeks[$bucketKey]['costs'] += (float) $cost->amount;
            }
        }

        $weekly = [];
        foreach ($weeks as $week) {
            $revenue = round($week['revenue'], 2);
            $costs = round($week['costs'], 2);
            $profit = round($revenue - $costs, 2);

            $weekly[] = [
                'week_start' => $week['week_start'],
                'week_end' => $week['week_end'],
                'revenue' => $revenue,
                'costs' => $costs,
                'profit' => $profit,
            ];
        }

        return response()->json([
            'year' => $year,
            'start_date' => $startOfYear->toDateString(),
            'end_date' => $endOfYear->toDateString(),
            'weeks' => $weekly,
        ]);
    }

    /**
     * @return array<string, array{week_start: string, week_end: string, revenue: float, costs: float}>
     */
    private function buildWeeklyBuckets(Carbon $startOfYear, Carbon $endOfYear): array
    {
        $buckets = [];
        $cursor = $startOfYear->copy();

        while ($cursor->lte($endOfYear)) {
            $weekStart = $cursor->copy()->startOfDay();
            $weekEnd = $cursor->copy()->addDays(6)->endOfDay();
            if ($weekEnd->gt($endOfYear)) {
                $weekEnd = $endOfYear->copy();
            }

            $buckets[$weekStart->toDateString()] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'revenue' => 0.0,
                'costs' => 0.0,
            ];

            $cursor->addDays(7)->startOfDay();
        }

        return $buckets;
    }

    private function resolveWeekBucketKey(Carbon $startOfYear, Carbon $endOfYear, Carbon $date): ?string
    {
        $date = $date->copy()->startOfDay();
        if ($date->lt($startOfYear) || $date->gt($endOfYear)) {
            return null;
        }

        $daysFromStart = $startOfYear->diffInDays($date);
        $bucketOffset = intdiv($daysFromStart, 7) * 7;

        return $startOfYear->copy()->addDays($bucketOffset)->toDateString();
    }
}
