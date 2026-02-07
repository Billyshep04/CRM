<?php

namespace App\Http\Controllers;

use App\Http\Resources\JobResource;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = Job::query()->with('customer')->latest();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->integer('per_page', 15);

        return JobResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'description' => ['required', 'string'],
            'cost' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'completed', 'invoiced'])],
            'completed_at' => ['nullable', 'date'],
            'invoiced_at' => ['nullable', 'date'],
        ]);

        $job = Job::create([
            ...$validated,
            'created_by_user_id' => $request->user()?->id,
            'status' => $validated['status'] ?? 'draft',
        ]);

        if ($job->status === 'completed' && !$job->completed_at) {
            $job->forceFill(['completed_at' => now()])->save();
        }

        if ($job->status === 'invoiced' && !$job->invoiced_at) {
            $job->forceFill(['invoiced_at' => now()])->save();
        }

        return new JobResource($job->load('customer'));
    }

    public function show(Job $job)
    {
        return new JobResource($job->load('customer'));
    }

    public function update(Request $request, Job $job)
    {
        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'description' => ['sometimes', 'string'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['draft', 'completed', 'invoiced'])],
            'completed_at' => ['nullable', 'date'],
            'invoiced_at' => ['nullable', 'date'],
        ]);

        $job->update($validated);

        if ($job->status === 'completed' && !$job->completed_at) {
            $job->forceFill(['completed_at' => now()])->save();
        }

        if ($job->status === 'invoiced' && !$job->invoiced_at) {
            $job->forceFill(['invoiced_at' => now()])->save();
        }

        return new JobResource($job->load('customer'));
    }

    public function destroy(Job $job)
    {
        $job->delete();

        return response()->json(['message' => 'Job deleted.']);
    }
}
