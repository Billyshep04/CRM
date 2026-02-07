<?php

namespace App\Http\Controllers;

use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index(Request $request)
    {
        $query = Website::query()->with('customer')->latest();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $perPage = $request->integer('per_page', 15);

        return WebsiteResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'login_url' => ['required', 'url', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        $website = Website::create($validated);

        return new WebsiteResource($website->load('customer'));
    }

    public function show(Website $website)
    {
        return new WebsiteResource($website->load('customer'));
    }

    public function update(Request $request, Website $website)
    {
        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'login_url' => ['sometimes', 'url', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        $website->update($validated);

        return new WebsiteResource($website->load('customer'));
    }

    public function destroy(Website $website)
    {
        $website->delete();

        return response()->json(['message' => 'Website deleted.']);
    }
}
