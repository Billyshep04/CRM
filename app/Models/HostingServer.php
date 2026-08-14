<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingServer extends Model
{
    protected $fillable = ['name', 'provider', 'hostname', 'server_type', 'api_type', 'credentials', 'status', 'metadata'];
    protected $hidden = ['credentials'];
    protected $casts = ['credentials' => 'encrypted:array', 'metadata' => 'array'];
    public function websites(): HasMany { return $this->hasMany(Website::class); }
    public function accounts(): HasMany { return $this->hasMany(HostingAccount::class); }
    public function packages(): HasMany { return $this->hasMany(HostingPackage::class); }
}
