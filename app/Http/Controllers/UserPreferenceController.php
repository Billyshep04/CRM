<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

    /**
     * @return array<string, bool>
     */
    private function defaultDashboardTiles(): array
    {
        return [
            'revenue' => true,
            'costs' => true,
            'profit' => true,
            'jobs' => true,
            'subscriptions' => true,
            'potential_mrr' => true,
            'pipeline_value' => true,
            'open_opportunities' => true,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, bool>
     */
    private function normalizeDashboardTiles($value): array
    {
        $defaults = $this->defaultDashboardTiles();
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

    private function hasDashboardTilesColumn(): bool
    {
        return Schema::hasColumn('user_preferences', 'dashboard_tiles');
    }

    /**
     * @return array<string, bool>
     */
    private function dashboardTilesFor(?UserPreference $preference): array
    {
        if (!$preference) {
            return $this->defaultDashboardTiles();
        }

        $storedTiles = $this->hasDashboardTilesColumn()
            ? $preference->dashboard_tiles
            : null;

        if (!is_array($storedTiles)) {
            $monthlyFinanceBoxes = $preference->monthly_finance_boxes;
            $storedTiles = is_array($monthlyFinanceBoxes)
                ? ($monthlyFinanceBoxes['dashboard_tiles'] ?? null)
                : null;
        }

        return $this->normalizeDashboardTiles($storedTiles);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $preference = $user->preference()->first();
        $monthlyFinanceBoxes = $this->normalizeMonthlyFinanceBoxes($preference?->monthly_finance_boxes);

        return response()->json([
            'theme' => $preference?->theme ?? 'light',
            'monthly_finance_boxes' => $monthlyFinanceBoxes,
            'dashboard_tiles' => $this->dashboardTilesFor($preference),
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
            'dashboard_tiles' => ['sometimes', 'array'],
            'dashboard_tiles.revenue' => ['sometimes', 'boolean'],
            'dashboard_tiles.costs' => ['sometimes', 'boolean'],
            'dashboard_tiles.profit' => ['sometimes', 'boolean'],
            'dashboard_tiles.jobs' => ['sometimes', 'boolean'],
            'dashboard_tiles.subscriptions' => ['sometimes', 'boolean'],
            'dashboard_tiles.potential_mrr' => ['sometimes', 'boolean'],
            'dashboard_tiles.pipeline_value' => ['sometimes', 'boolean'],
            'dashboard_tiles.open_opportunities' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if (!array_key_exists('theme', $validated)
            && !array_key_exists('monthly_finance_boxes', $validated)
            && !array_key_exists('dashboard_tiles', $validated)) {
            return response()->json([
                'message' => 'No preference fields supplied.',
            ], 422);
        }

        $defaultTheme = $validated['theme'] ?? 'light';
        $defaultBoxes = $this->defaultMonthlyFinanceBoxes();
        $defaultDashboardTiles = $this->defaultDashboardTiles();

        $createValues = [
            'theme' => $defaultTheme,
            'monthly_finance_boxes' => $defaultBoxes,
        ];
        if ($this->hasDashboardTilesColumn()) {
            $createValues['dashboard_tiles'] = $defaultDashboardTiles;
        }

        $preference = UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            $createValues
        );

        $updates = [];

        if (array_key_exists('theme', $validated) && $preference->theme !== $validated['theme']) {
            $updates['theme'] = $validated['theme'];
        }

        if (array_key_exists('monthly_finance_boxes', $validated)) {
            $currentBoxes = $this->normalizeMonthlyFinanceBoxes($preference->monthly_finance_boxes);
            $incomingBoxes = $this->normalizeMonthlyFinanceBoxes($validated['monthly_finance_boxes']);
            $storedMonthlyFinanceBoxes = $preference->monthly_finance_boxes;
            if (is_array($storedMonthlyFinanceBoxes)
                && array_key_exists('dashboard_tiles', $storedMonthlyFinanceBoxes)) {
                $incomingBoxes['dashboard_tiles'] = $storedMonthlyFinanceBoxes['dashboard_tiles'];
            }
            if ($currentBoxes !== $incomingBoxes) {
                $updates['monthly_finance_boxes'] = $incomingBoxes;
            }
        }

        if (array_key_exists('dashboard_tiles', $validated)) {
            $currentDashboardTiles = $this->dashboardTilesFor($preference);
            $incomingDashboardTiles = $this->normalizeDashboardTiles($validated['dashboard_tiles']);
            if ($currentDashboardTiles !== $incomingDashboardTiles) {
                if ($this->hasDashboardTilesColumn()) {
                    $updates['dashboard_tiles'] = $incomingDashboardTiles;
                } else {
                    $monthlyFinanceBoxes = $updates['monthly_finance_boxes']
                        ?? $preference->monthly_finance_boxes
                        ?? $defaultBoxes;
                    $monthlyFinanceBoxes['dashboard_tiles'] = $incomingDashboardTiles;
                    $updates['monthly_finance_boxes'] = $monthlyFinanceBoxes;
                }
            }
        }

        if ($updates !== []) {
            $preference->update($updates);
        }

        return response()->json([
            'theme' => $preference->theme,
            'monthly_finance_boxes' => $this->normalizeMonthlyFinanceBoxes($preference->monthly_finance_boxes),
            'dashboard_tiles' => $this->dashboardTilesFor($preference),
        ]);
    }
}
