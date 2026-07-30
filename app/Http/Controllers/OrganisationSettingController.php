<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganisationSettingsRequest;
use App\Services\OrganisationSettings;
use Illuminate\Http\JsonResponse;

class OrganisationSettingController extends Controller
{
    public function public(OrganisationSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json(['data' => collect($all)->only(['company_name', 'primary_colour', 'accent_colour', 'background_colour', 'surface_colour', 'dark_background_colour', 'dark_surface_colour', 'login_title', 'footer_text'])->all()]);
    }

    public function show(OrganisationSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all()]);
    }

    public function update(UpdateOrganisationSettingsRequest $request, OrganisationSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->update($request->validated(), $request->user()->id)]);
    }
}
