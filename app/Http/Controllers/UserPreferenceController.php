<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    /**
     * @return array<string, bool>
     */
    private function defaultMonthlyFinanceBoxes(): array
    {
        return [
            'revenue' => true,
            'costs' => true,
            'profit' => true,
            'tax' => true,
            'owed' => true,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, bool>
     */
    private function normalizeMonthlyFinanceBoxes($value): array
    {
        $defaults = $this->defaultMonthlyFinanceBoxes();
        if (!is_array($value)) {
            return $defaults;
        }

        $normalized = $defaults;
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $value)) {
                $normalized[$key] = (bool) $value[$key];
            }
        }

        return $normalized;
    }

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $preference = $user->preference;
        $monthlyFinanceBoxes = $this->normalizeMonthlyFinanceBoxes($preference?->monthly_finance_boxes);

        return response()->json([
            'theme' => $preference?->theme ?? 'light',
            'monthly_finance_boxes' => $monthlyFinanceBoxes,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['sometimes', 'in:light,dark'],
            'monthly_finance_boxes' => ['sometimes', 'array'],
            'monthly_finance_boxes.revenue' => ['sometimes', 'boolean'],
            'monthly_finance_boxes.costs' => ['sometimes', 'boolean'],
            'monthly_finance_boxes.profit' => ['sometimes', 'boolean'],
            'monthly_finance_boxes.tax' => ['sometimes', 'boolean'],
            'monthly_finance_boxes.owed' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if (!array_key_exists('theme', $validated) && !array_key_exists('monthly_finance_boxes', $validated)) {
            return response()->json([
                'message' => 'No preference fields supplied.',
            ], 422);
        }

        $defaultTheme = $validated['theme'] ?? 'light';
        $defaultBoxes = $this->defaultMonthlyFinanceBoxes();

        $preference = UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => $defaultTheme,
                'monthly_finance_boxes' => $defaultBoxes,
            ]
        );

        $updates = [];

        if (array_key_exists('theme', $validated) && $preference->theme !== $validated['theme']) {
            $updates['theme'] = $validated['theme'];
        }

        if (array_key_exists('monthly_finance_boxes', $validated)) {
            $currentBoxes = $this->normalizeMonthlyFinanceBoxes($preference->monthly_finance_boxes);
            $incomingBoxes = $this->normalizeMonthlyFinanceBoxes($validated['monthly_finance_boxes']);
            if ($currentBoxes !== $incomingBoxes) {
                $updates['monthly_finance_boxes'] = $incomingBoxes;
            }
        }

        if ($updates !== []) {
            $preference->update($updates);
        }

        return response()->json([
            'theme' => $preference->theme,
            'monthly_finance_boxes' => $this->normalizeMonthlyFinanceBoxes($preference->monthly_finance_boxes),
        ]);
    }
}
