<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('slug', 'staff');
            })
            ->latest()
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->assignRole('staff');

        return response()->json([
            'message' => 'Staff user created.',
            'data' => $user->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }
}
