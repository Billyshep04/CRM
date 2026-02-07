<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSetting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'logo_file_id',
        'updated_by_user_id',
    ];

    protected $with = [
        'logoFile',
    ];

    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
