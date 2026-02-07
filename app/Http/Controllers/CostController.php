<?php

namespace App\Http\Controllers;

use App\Http\Resources\CostResource;
use App\Models\Cost;
use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CostController extends Controller
{
    public function index(Request $request)
    {
        $query = Cost::query()
            ->with('receiptFile')
            ->orderByDesc('incurred_on')
            ->latest();

        $perPage = $request->integer('per_page', 15);

        return CostResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'incurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
        ]);

        $receiptFileId = $this->storeReceipt($request);

        $cost = Cost::create([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'incurred_on' => $validated['incurred_on'],
            'notes' => $validated['notes'] ?? null,
            'receipt_file_id' => $receiptFileId,
            'created_by_user_id' => $request->user()?->id,
        ]);

        if ($receiptFileId) {
            StoredFile::query()->whereKey($receiptFileId)->update([
                'owner_id' => $cost->id,
            ]);
        }

        return new CostResource($cost->load('receiptFile'));
    }

    public function show(Cost $cost)
    {
        return new CostResource($cost->load('receiptFile'));
    }

    public function update(Request $request, Cost $cost)
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'incurred_on' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:5120'],
        ]);

        $receiptFileId = $this->storeReceipt($request);
        if ($receiptFileId) {
            if ($cost->receiptFile) {
                Storage::disk($cost->receiptFile->disk)->delete($cost->receiptFile->path);
                $cost->receiptFile->delete();
            }
            $validated['receipt_file_id'] = $receiptFileId;
        }

        $cost->update($validated);

        if ($receiptFileId) {
            StoredFile::query()->whereKey($receiptFileId)->update([
                'owner_id' => $cost->id,
            ]);
        }

        return new CostResource($cost->load('receiptFile'));
    }

    public function destroy(Cost $cost)
    {
        $cost->delete();

        return response()->json(['message' => 'Cost deleted.']);
    }

    public function downloadReceipt(Cost $cost)
    {
        $cost->loadMissing('receiptFile');

        if (!$cost->receiptFile) {
            abort(404, 'Receipt not found.');
        }

        return Storage::disk($cost->receiptFile->disk)->download(
            $cost->receiptFile->path,
            $cost->receiptFile->original_name
        );
    }

    private function storeReceipt(Request $request): ?int
    {
        if (!$request->hasFile('receipt')) {
            return null;
        }

        $file = $request->file('receipt');
        if (!$file) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'receipt-' . Str::uuid()->toString() . '.' . $extension;
        $path = "receipts/{$name}";
        $disk = 'private';

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            abort(422, 'Unable to read receipt file.');
        }

        Storage::disk($disk)->put($path, $contents);

        $storedFile = StoredFile::create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName() ?: $name,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => 'receipt',
            'checksum' => hash('sha256', $contents),
            'is_private' => true,
            'uploaded_by_user_id' => $request->user()?->id,
            'owner_type' => Cost::class,
            'owner_id' => null,
        ]);

        return $storedFile->id;
    }
}
