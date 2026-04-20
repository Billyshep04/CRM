<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use App\Models\Invoice;
use App\Models\Job;
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

        $completedJobsTotal = $this->calculateCompletedJobsTotalForRange($startOfMonth, $endOfMonth);

        $paidSubscriptionsTotal = 0.0;
        if (Schema::hasTable('subscription_months')) {
            $paidSubscriptionsTotal = $this->calculatePaidSubscriptionsTotalForMonth($startOfMonth);
        }

        $monthlyCostsTotal = 0.0;
        $monthlyCostsTotal = $this->calculateCostsTotalForRange($startOfMonth, $endOfMonth);

        $revenueTotal = $completedJobsTotal + $paidSubscriptionsTotal;
        $profitTotal = $revenueTotal - $monthlyCostsTotal;

        return response()->json([
            'completed_jobs_total' => $completedJobsTotal,
            'active_subscriptions_total' => $paidSubscriptionsTotal,
            'paid_subscriptions_total' => $paidSubscriptionsTotal,
            'costs_total' => $monthlyCostsTotal,
            'profit_total' => $profitTotal,
            'total' => $revenueTotal,
        ]);
    }

    public function monthlyFinance(): JsonResponse
    {
        $now = now();
        $startMonth = Carbon::create(
            $now->year,
            2,
            1,
            0,
            0,
            0,
            config('app.timezone')
        )->startOfMonth();

        if ($now->month < 2) {
            $startMonth->subYearNoOverflow();
        }

        $endMonth = $now->copy()->startOfMonth();
        $months = [];
        $cursor = $startMonth->copy();
        $hasSubscriptionMonths = Schema::hasTable('subscription_months');

        while ($cursor->lte($endMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $completedJobsTotal = $this->calculateCompletedJobsTotalForRange($monthStart, $monthEnd);
            $paidSubscriptionsTotal = $hasSubscriptionMonths
                ? $this->calculatePaidSubscriptionsTotalForMonth($monthStart)
                : 0.0;
            $costsTotal = $this->calculateCostsTotalForRange($monthStart, $monthEnd);
            $revenueTotal = $completedJobsTotal + $paidSubscriptionsTotal;
            $profitTotal = $revenueTotal - $costsTotal;
            $taxTotal = $profitTotal * 0.2;
            $owedTotal = $this->calculateOwedTotalForRange($monthStart, $monthEnd);

            $months[] = [
                'month_start' => $monthStart->toDateString(),
                'month_end' => $monthEnd->toDateString(),
                'label' => $monthStart->format('F Y'),
                'completed_jobs_total' => round($completedJobsTotal, 2),
                'paid_subscriptions_total' => round($paidSubscriptionsTotal, 2),
                'revenue_total' => round($revenueTotal, 2),
                'costs_total' => round($costsTotal, 2),
                'profit_total' => round($profitTotal, 2),
                'tax_total' => round($taxTotal, 2),
                'owed_total' => round($owedTotal, 2),
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return response()->json([
            'start_month' => $startMonth->toDateString(),
            'end_month' => $endMonth->toDateString(),
            'selected_month' => $endMonth->toDateString(),
            'months' => $months,
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
            $hasRecurringColumns = Schema::hasColumn('costs', 'is_recurring')
                && Schema::hasColumn('costs', 'recurring_frequency');

            $selectColumns = ['amount', 'incurred_on'];
            if ($hasRecurringColumns) {
                $selectColumns[] = 'is_recurring';
                $selectColumns[] = 'recurring_frequency';
            }

            $costs = Cost::query()
                ->whereDate('incurred_on', '<=', $endOfYear->toDateString())
                ->get($selectColumns);

            foreach ($costs as $cost) {
                $this->addCostToWeeklyBuckets($weeks, $startOfYear, $endOfYear, $cost, $hasRecurringColumns);
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

    private function calculateCostsTotalForRange(Carbon $startDate, Carbon $endDate): float
    {
        if (!Schema::hasTable('costs')) {
            return 0.0;
        }

        $hasRecurringColumns = Schema::hasColumn('costs', 'is_recurring')
            && Schema::hasColumn('costs', 'recurring_frequency');

        if (!$hasRecurringColumns) {
            return (float) Cost::query()
                ->whereBetween('incurred_on', [$startDate->toDateString(), $endDate->toDateString()])
                ->sum('amount');
        }

        $costs = Cost::query()
            ->whereDate('incurred_on', '<=', $endDate->toDateString())
            ->get(['amount', 'incurred_on', 'is_recurring', 'recurring_frequency']);

        $total = 0.0;
        foreach ($costs as $cost) {
            $total += $this->calculateCostAmountForRange($cost, $startDate, $endDate, true);
        }

        return $total;
    }

    private function calculateCompletedJobsTotalForRange(Carbon $startDate, Carbon $endDate): float
    {
        return (float) Job::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($startDate, $endDate): void {
                $query
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->orWhere(function ($fallback) use ($startDate, $endDate): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    });
            })
            ->sum('cost');
    }

    private function calculatePaidSubscriptionsTotalForMonth(Carbon $monthStart): float
    {
        $paidSubscriptionMonths = SubscriptionMonth::query()
            ->with('subscription:id,monthly_cost')
            ->where('payment_status', 'paid')
            ->whereDate('month_start', $monthStart->toDateString())
            ->get(['subscription_id']);

        return (float) $paidSubscriptionMonths->sum(
            static fn (SubscriptionMonth $month): float => (float) ($month->subscription?->monthly_cost ?? 0)
        );
    }

    private function calculateOwedTotalForRange(Carbon $startDate, Carbon $endDate): float
    {
        return (float) Invoice::query()
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', today())
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('total');
    }

    private function addCostToWeeklyBuckets(
        array &$weeks,
        Carbon $startOfYear,
        Carbon $endOfYear,
        Cost $cost,
        bool $hasRecurringColumns
    ): void {
        if (!$cost->incurred_on) {
            return;
        }

        $incurredOn = Carbon::parse($cost->incurred_on)->startOfDay();
        $amount = (float) $cost->amount;
        $frequency = $hasRecurringColumns ? (string) ($cost->recurring_frequency ?? '') : '';
        $isRecurring = $hasRecurringColumns
            && (bool) ($cost->is_recurring ?? false)
            && in_array($frequency, ['monthly', 'annual'], true);

        if (!$isRecurring) {
            $bucketKey = $this->resolveWeekBucketKey($startOfYear, $endOfYear, $incurredOn);
            if ($bucketKey) {
                $weeks[$bucketKey]['costs'] += $amount;
            }

            return;
        }

        $occurrence = $incurredOn->copy();
        $guard = 0;

        while ($occurrence->lte($endOfYear) && $guard < 5000) {
            if ($occurrence->gte($startOfYear)) {
                $bucketKey = $this->resolveWeekBucketKey($startOfYear, $endOfYear, $occurrence);
                if ($bucketKey) {
                    $weeks[$bucketKey]['costs'] += $amount;
                }
            }

            $occurrence = $this->nextRecurringDate($occurrence, $frequency);
            $guard++;
        }
    }

    private function calculateCostAmountForRange(
        Cost $cost,
        Carbon $startDate,
        Carbon $endDate,
        bool $hasRecurringColumns
    ): float {
        if (!$cost->incurred_on) {
            return 0.0;
        }

        $incurredOn = Carbon::parse($cost->incurred_on)->startOfDay();
        $amount = (float) $cost->amount;
        $frequency = $hasRecurringColumns ? (string) ($cost->recurring_frequency ?? '') : '';
        $isRecurring = $hasRecurringColumns
            && (bool) ($cost->is_recurring ?? false)
            && in_array($frequency, ['monthly', 'annual'], true);

        if (!$isRecurring) {
            return $incurredOn->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())
                ? $amount
                : 0.0;
        }

        $total = 0.0;
        $occurrence = $incurredOn->copy();
        $guard = 0;

        while ($occurrence->lte($endDate) && $guard < 5000) {
            if ($occurrence->gte($startDate)) {
                $total += $amount;
            }

            $occurrence = $this->nextRecurringDate($occurrence, $frequency);
            $guard++;
        }

        return $total;
    }

    private function nextRecurringDate(Carbon $date, string $frequency): Carbon
    {
        return $frequency === 'annual'
            ? $date->copy()->addYearNoOverflow()->startOfDay()
            : $date->copy()->addMonthNoOverflow()->startOfDay();
    }
}
