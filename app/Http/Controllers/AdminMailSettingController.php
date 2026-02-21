<?php

namespace App\Http\Controllers;

use App\Services\AdminMailSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminMailSettingController extends Controller
{
    public function __construct(private readonly AdminMailSettings $settings)
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
            'smtp2go_enabled' => ['required', 'boolean'],
            'smtp2go_api_key' => ['nullable', 'string', 'min:10', 'max:255'],
        ]);

        $enabled = (bool) $validated['smtp2go_enabled'];
        $apiKey = isset($validated['smtp2go_api_key'])
            ? trim((string) $validated['smtp2go_api_key'])
            : null;

        if ($enabled && ($apiKey === null || $apiKey === '') && !$this->settings->hasSmtp2goApiKey()) {
            throw ValidationException::withMessages([
                'smtp2go_api_key' => ['API key is required when SMTP2GO is enabled.'],
            ]);
        }

        return response()->json([
            'data' => $this->settings->updateSmtp2go($enabled, $apiKey !== '' ? $apiKey : null),
        ]);
    }
}
