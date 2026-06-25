<?php

namespace App\Http\Controllers;

use App\Services\ProposalFormSettings;
use Illuminate\Http\Request;

class AdminProposalFormController extends Controller
{
    public function __construct(private readonly ProposalFormSettings $settings)
    {
    }

    public function show()
    {
        return response()->json([
            'data' => $this->settings->adminPayload(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*.slug' => ['nullable', 'string', 'max:100'],
            'types.*.label' => ['required', 'string', 'max:255'],
            'types.*.sort_order' => ['nullable', 'integer'],
            'types.*.questions' => ['nullable', 'array'],
            'types.*.questions.*.key' => ['nullable', 'string', 'max:100'],
            'types.*.questions.*.label' => ['required', 'string', 'max:255'],
            'types.*.questions.*.type' => ['nullable', 'string', 'max:30'],
            'types.*.questions.*.required' => ['nullable', 'boolean'],
            'types.*.questions.*.options' => ['nullable', 'array'],
            'types.*.questions.*.options.*' => ['nullable', 'string', 'max:255'],
            'types.*.questions.*.sort_order' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'data' => $this->settings->update($validated['types']),
        ]);
    }
}
