<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrandSettingResource;
use App\Models\BrandSetting;
use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandSettingController extends Controller
{
    public function show(): BrandSettingResource
    {
        $setting = BrandSetting::query()->with('logoFile')->first();

        if (!$setting) {
            $setting = BrandSetting::query()->create();
        }

        return new BrandSettingResource($setting);
    }

    public function updateLogo(Request $request): BrandSettingResource
    {
        $validated = $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $setting = BrandSetting::query()->first();
        if (!$setting) {
            $setting = BrandSetting::query()->create();
        }

        $file = $validated['logo'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'logo-' . Str::uuid()->toString() . '.' . $extension;
        $path = "branding/{$name}";

        $disk = 'private';
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            abort(422, 'Unable to read logo file.');
        }
        Storage::disk($disk)->put($path, $contents);

        $storedFile = StoredFile::create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName() ?: $name,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => 'brand_logo',
            'checksum' => hash('sha256', $contents),
            'is_private' => true,
            'uploaded_by_user_id' => $request->user()?->id,
            'owner_type' => BrandSetting::class,
            'owner_id' => $setting->id,
        ]);

        if ($setting->logoFile) {
            Storage::disk($setting->logoFile->disk)->delete($setting->logoFile->path);
            $setting->logoFile->delete();
        }

        $setting->update([
            'logo_file_id' => $storedFile->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return new BrandSettingResource($setting->load('logoFile'));
    }

    public function logo()
    {
        $setting = BrandSetting::query()->with('logoFile')->first();

        if (!$setting || !$setting->logoFile) {
            abort(404, 'Logo not found.');
        }

        return Storage::disk($setting->logoFile->disk)->response(
            $setting->logoFile->path,
            $setting->logoFile->original_name,
            [
                'Content-Type' => $setting->logoFile->mime_type ?? 'image/png',
            ]
        );
    }
}
