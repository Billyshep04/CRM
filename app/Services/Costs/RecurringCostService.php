<?php

namespace App\Services\Costs;

use App\Models\Cost;
use App\Models\RecurringCost;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class RecurringCostService
{
    public function entriesForMonth(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $oneOffs = Cost::query()->with('receiptFile')->where('is_recurring', false)->whereBetween('incurred_on', [$start->toDateString(), $end->toDateString()])->orderBy('incurred_on')->get();
        $entries = $oneOffs->map(fn (Cost $cost) => [
            'id' => 'cost-'.$cost->id, 'cost_id' => $cost->id, 'recurring_cost_id' => null, 'entry_type' => 'one_off',
            'description' => $cost->description, 'amount' => (float) $cost->amount, 'incurred_on' => $cost->incurred_on?->toDateString(),
            'notes' => $cost->notes, 'receipt_file_id' => $cost->receipt_file_id,
        ]);

        foreach ($this->legacyOccurrences($start, $end) as $occurrence) {
            $entries->push($occurrence);
        }
        foreach ($this->recurringOccurrences($start, $end) as $occurrence) {
            $entries->push($occurrence);
        }

        return $entries->sortBy('incurred_on')->values()->all();
    }

    public function totalForRange(Carbon $start, Carbon $end): float
    {
        $oneOff = (float) Cost::query()->where('is_recurring', false)->whereBetween('incurred_on', [$start->toDateString(), $end->toDateString()])->sum('amount');
        $legacy = Cost::query()->where('is_recurring', true)->get()->sum(fn (Cost $cost) => $this->legacyAmount($cost, $start, $end));
        $recurring = Schema::hasTable('recurring_costs') ? collect($this->recurringOccurrences($start, $end))->sum('amount') : 0;

        return $oneOff + $legacy + $recurring;
    }

    public function recurringOccurrences(Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('recurring_costs')) {
            return [];
        }
        $schedules = RecurringCost::query()->with('rates')->whereDate('starts_on', '<=', $end->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))->get();
        $occurrences = [];
        foreach ($schedules as $schedule) {
            $origin = Carbon::parse($schedule->starts_on)->startOfDay();
            $date = $origin->copy();
            $guard = 0;
            $interval = 0;
            while ($date->lte($end) && $guard++ < 5000) {
                if ($date->gte($start) && (! $schedule->ends_on || $date->lte($schedule->ends_on))) {
                    $rate = $schedule->rates->filter(fn ($rate) => $rate->effective_from->lte($date))->sortByDesc('effective_from')->first();
                    if ($rate) {
                        $occurrences[] = ['id' => 'recurring-'.$schedule->id.'-'.$date->format('Y-m'), 'cost_id' => null, 'recurring_cost_id' => $schedule->id, 'entry_type' => 'recurring', 'description' => $schedule->description, 'amount' => (float) $rate->amount, 'incurred_on' => $date->toDateString(), 'notes' => $schedule->notes, 'receipt_file_id' => $schedule->receipt_file_id];
                    }
                }
                $interval++;
                $date = $schedule->frequency === 'annual'
                    ? $origin->copy()->addYearsNoOverflow($interval)
                    : $origin->copy()->addMonthsNoOverflow($interval);
            }
        }

        return $occurrences;
    }

    private function legacyOccurrences(Carbon $start, Carbon $end): array
    {
        return Cost::query()->where('is_recurring', true)->get()->flatMap(function (Cost $cost) use ($start, $end) {
            $origin = Carbon::parse($cost->incurred_on)->startOfDay();
            $date = $origin->copy();
            $items = [];
            $interval = 0;
            $guard = 0;
            while ($date->lte($end) && $guard++ < 5000) {
                if ($date->gte($start)) {
                    $items[] = ['id' => 'legacy-'.$cost->id.'-'.$date->format('Y-m'), 'cost_id' => $cost->id, 'recurring_cost_id' => null, 'entry_type' => 'recurring', 'description' => $cost->description, 'amount' => (float) $cost->amount, 'incurred_on' => $date->toDateString(), 'notes' => $cost->notes, 'receipt_file_id' => $cost->receipt_file_id];
                }
                $interval++;
                $date = $cost->recurring_frequency === 'annual' ? $origin->copy()->addYearsNoOverflow($interval) : $origin->copy()->addMonthsNoOverflow($interval);
            }

            return $items;
        })->all();
    }

    private function legacyAmount(Cost $cost, Carbon $start, Carbon $end): float
    {
        $date = Carbon::parse($cost->incurred_on)->startOfDay();
        $total = 0.0;
        $guard = 0;
        while ($date->lte($end) && $guard++ < 5000) {
            if ($date->gte($start)) {
                $total += (float) $cost->amount;
            }
            $date = $cost->recurring_frequency === 'annual' ? $date->addYearNoOverflow() : $date->addMonthNoOverflow();
        }

        return $total;
    }
}
