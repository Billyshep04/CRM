<?php

namespace App\Http\Controllers;

use App\Models\RecurringCost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringCostController extends Controller
{
    public function index()
    {
        return response()->json(['data' => RecurringCost::query()->with('rates')->orderBy('description')->get()->map(fn ($cost) => $this->present($cost))->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => ['required', 'string'], 'amount' => ['required', 'numeric', 'min:0'],
            'starts_on' => ['required', 'date'], 'frequency' => ['required', Rule::in(['monthly', 'annual'])], 'notes' => ['nullable', 'string'],
        ]);
        $cost = RecurringCost::create([
            'description' => $data['description'], 'frequency' => $data['frequency'], 'starts_on' => $data['starts_on'],
            'active' => true, 'notes' => $data['notes'] ?? null, 'created_by_user_id' => $request->user()?->id,
        ]);
        $cost->rates()->create(['amount' => $data['amount'], 'effective_from' => $data['starts_on']]);

        return response()->json(['data' => $this->present($cost->load('rates'))], 201);
    }

    public function update(Request $request, RecurringCost $recurringCost)
    {
        $data = $request->validate([
            'description' => ['sometimes', 'required', 'string'], 'frequency' => ['sometimes', Rule::in(['monthly', 'annual'])],
            'notes' => ['nullable', 'string'], 'amount' => ['sometimes', 'numeric', 'min:0'], 'effective_from' => ['required_with:amount', 'date'],
        ]);
        $recurringCost->update(collect($data)->only(['description', 'frequency', 'notes'])->all());
        if (array_key_exists('amount', $data)) {
            $effective = Carbon::parse($data['effective_from'])->startOfMonth();
            if ($effective->lt(now()->startOfMonth())) {
                abort(422, 'A new recurring price cannot start in a previous month.');
            }
            $recurringCost->rates()->updateOrCreate(['effective_from' => $effective->toDateString()], ['amount' => $data['amount']]);
        }

        return response()->json(['data' => $this->present($recurringCost->fresh('rates'))]);
    }

    public function destroy(Request $request, RecurringCost $recurringCost)
    {
        $data = $request->validate(['ends_on' => ['nullable', 'date']]);
        $endsOn = isset($data['ends_on']) ? Carbon::parse($data['ends_on'])->endOfMonth() : now()->endOfMonth();
        $recurringCost->update(['active' => false, 'ends_on' => $endsOn->toDateString()]);

        return response()->json(['message' => 'Recurring cost stopped. Previous monthly costs have been preserved.']);
    }

    private function present(RecurringCost $cost): array
    {
        $rates = $cost->rates->sortBy('effective_from')->values();
        $current = $rates->filter(fn ($rate) => $rate->effective_from->lte(today()))->last() ?? $rates->first();

        return [
            'id' => $cost->id, 'description' => $cost->description, 'frequency' => $cost->frequency,
            'starts_on' => $cost->starts_on?->toDateString(), 'ends_on' => $cost->ends_on?->toDateString(), 'active' => $cost->active,
            'notes' => $cost->notes, 'current_amount' => (float) ($current?->amount ?? 0),
            'rates' => $rates->map(fn ($rate) => ['id' => $rate->id, 'amount' => (float) $rate->amount, 'effective_from' => $rate->effective_from->toDateString()])->all(),
        ];
    }
}
