<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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
        $customerProfiles = collect();
        if ($user) {
            $customerProfiles = Customer::query()
                ->where('user_id', $user->id)
                ->get();

            if ($customerProfiles->isEmpty() && $user->email) {
                $customerProfiles = Customer::query()
                    ->where('email', $user->email)
                    ->get();
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'billing_address' => $customerProfiles->isNotEmpty()
                ? ['required', 'string']
                : ['nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($user, $customerProfiles, $validated): void {
            if ($user) {
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
            }

            if ($customerProfiles->isNotEmpty()) {
                foreach ($customerProfiles as $customerProfile) {
                    $customerUpdates = [
                        'user_id' => $user?->id,
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'billing_address' => $validated['billing_address'],
                    ];
                    if (array_key_exists('phone', $validated)) {
                        $customerUpdates['phone'] = $validated['phone'];
                    }
                    $customerProfile->update($customerUpdates);
                }
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
