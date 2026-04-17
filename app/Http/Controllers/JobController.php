<?php

namespace App\Http\Controllers;

use App\Http\Resources\JobResource;
use App\Http\Resources\StoredFileResource;
use App\Models\Job;
use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

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

    public function photos(Job $job)
    {
        return StoredFileResource::collection(
            $job->photos()
                ->latest()
                ->get()
        );
    }

    public function uploadPhotos(Request $request, Job $job)
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:8192'],
        ]);

        $created = [];

        foreach ($validated['photos'] as $photo) {
            $extension = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
            $name = 'job-photo-' . Str::uuid()->toString() . '.' . $extension;
            $path = "jobs/{$job->id}/photos/{$name}";
            $disk = 'private';

            $contents = file_get_contents($photo->getRealPath());
            if ($contents === false) {
                continue;
            }

            Storage::disk($disk)->put($path, $contents);

            $created[] = StoredFile::create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName() ?: $name,
                'mime_type' => $photo->getClientMimeType(),
                'size' => $photo->getSize(),
                'category' => 'job_photo',
                'checksum' => hash('sha256', $contents),
                'is_private' => true,
                'uploaded_by_user_id' => $request->user()?->id,
                'owner_type' => Job::class,
                'owner_id' => $job->id,
            ]);
        }

        return response()->json([
            'message' => 'Photos uploaded.',
            'data' => StoredFileResource::collection(collect($created))->resolve(),
        ]);
    }

    public function downloadPhoto(Job $job, StoredFile $file)
    {
        if (!$this->isJobPhoto($job, $file)) {
            abort(404, 'Photo not found.');
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function downloadAllPhotos(Job $job)
    {
        $photos = $job->photos()->latest()->get();
        if ($photos->isEmpty()) {
            abort(404, 'No photos found for this job.');
        }

        if (!class_exists(ZipArchive::class)) {
            abort(500, 'ZIP extension is not available on this server.');
        }

        $temporaryDirectory = storage_path('app/private/tmp');
        if (!is_dir($temporaryDirectory) && !mkdir($temporaryDirectory, 0755, true) && !is_dir($temporaryDirectory)) {
            abort(500, 'Unable to prepare temporary ZIP directory.');
        }

        $zipFilename = "job-{$job->id}-photos-" . now()->format('YmdHis') . '.zip';
        $zipPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $zipFilename;

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            abort(500, 'Unable to create ZIP archive.');
        }

        $usedNames = [];
        foreach ($photos as $photo) {
            $absolutePath = Storage::disk($photo->disk)->path($photo->path);
            if (!is_file($absolutePath)) {
                continue;
            }

            $filename = $this->uniqueFilename($photo->original_name ?: basename($photo->path), $usedNames);
            $zip->addFile($absolutePath, $filename);
        }

        $zip->close();

        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function isJobPhoto(Job $job, StoredFile $file): bool
    {
        return $file->category === 'job_photo'
            && $file->owner_type === Job::class
            && (int) $file->owner_id === (int) $job->id;
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function uniqueFilename(string $originalName, array &$usedNames): string
    {
        $clean = trim($originalName) !== '' ? $originalName : 'photo.jpg';
        if (!isset($usedNames[$clean])) {
            $usedNames[$clean] = true;
            return $clean;
        }

        $info = pathinfo($clean);
        $name = $info['filename'] ?? 'photo';
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

        $counter = 2;
        do {
            $candidate = "{$name}-{$counter}{$extension}";
            $counter++;
        } while (isset($usedNames[$candidate]));

        $usedNames[$candidate] = true;
        return $candidate;
    }
}
