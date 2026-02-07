<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $preference = $user->preference;

        return response()->json([
            'theme' => $preference?->theme ?? 'light',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $preference = UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['theme' => $validated['theme']]
        );

        if ($preference->theme !== $validated['theme']) {
            $preference->update(['theme' => $validated['theme']]);
        }

        return response()->json([
            'theme' => $preference->theme,
        ]);
    }
}
