<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredFile extends Model
{
    use HasFactory;

    protected $table = 'files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'category',
        'checksum',
        'is_private',
        'uploaded_by_user_id',
        'owner_type',
        'owner_id',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'metadata' => 'array',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
