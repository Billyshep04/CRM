<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $customerProfile = $user?->customerProfile;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'billing_address' => $customerProfile
                ? ['required', 'string']
                : ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($user, $customerProfile, $validated): void {
            if ($user) {
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
            }

            if ($customerProfile) {
                $customerProfile->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'billing_address' => $validated['billing_address'],
                ]);
            }
        });

        $freshUser = $user?->fresh()?->load(['roles', 'customerProfile']);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $freshUser,
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }
}
