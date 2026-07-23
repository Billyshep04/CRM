<?php

namespace App\Http\Controllers;

use App\Enums\LeadScoreFactor;
use App\Http\Resources\LeadScoringProfileResource;
use App\Models\LeadScoringProfile;
use App\Services\LeadScoring\DefaultLeadScoringProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadScoringProfileController extends Controller
{
    public function index(DefaultLeadScoringProfile $defaults): AnonymousResourceCollection
    {
        $defaults->resolve();

        return LeadScoringProfileResource::collection(LeadScoringProfile::query()->with('weights')->latest()->get());
    }

    public function store(Request $request): LeadScoringProfileResource
    {
        $data = $this->validated($request);
        $profile = $this->createVersion($data, 1, $request->user()->id);

        return new LeadScoringProfileResource($profile);
    }

    public function update(Request $request, LeadScoringProfile $leadScoringProfile): LeadScoringProfileResource
    {
        $data = $this->validated($request);
        $data['is_default'] ??= $leadScoringProfile->is_default;
        $profile = DB::transaction(function () use ($leadScoringProfile, $data, $request) {
            $leadScoringProfile->update(['is_active' => false]);

            return $this->createVersion($data, $leadScoringProfile->version + 1, $request->user()->id);
        });

        return new LeadScoringProfileResource($profile);
    }

    private function validated(Request $request): array
    {
        $factors = array_column(LeadScoreFactor::cases(), 'value');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'is_default' => ['sometimes', 'boolean'], 'weights' => ['required', 'array'], 'weights.*' => ['numeric', 'min:0', 'max:100'], 'weights' => [Rule::requiredIf(true), 'array:'.implode(',', $factors)]]);
        if (array_sum($data['weights']) <= 0) {
            abort(422, 'At least one scoring weight must be greater than zero.');
        }

        return $data;
    }

    private function createVersion(array $data, int $version, int $userId): LeadScoringProfile
    {
        return DB::transaction(function () use ($data, $version, $userId) {
            if ($data['is_default'] ?? false) {
                LeadScoringProfile::query()->where('is_default', true)->update(['is_default' => false]);
            }
            $profile = LeadScoringProfile::query()->create(['public_id' => (string) Str::ulid(), 'created_by_user_id' => $userId, 'name' => $data['name'], 'version' => $version, 'description' => $data['description'] ?? null, 'is_default' => $data['is_default'] ?? false, 'is_active' => true]);
            $profile->weights()->createMany(collect($data['weights'])->map(fn ($weight, $factor) => ['factor' => $factor, 'weight' => $weight, 'is_enabled' => $weight > 0])->values()->all());

            return $profile->load('weights');
        });
    }
}
