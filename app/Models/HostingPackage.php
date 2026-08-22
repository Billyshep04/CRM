<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HostingPackage extends Model { protected $guarded=['id']; protected $casts=['limits'=>'array','shell_access'=>'boolean','active'=>'boolean','last_synced_at'=>'datetime']; public function server(){return $this->belongsTo(HostingServer::class,'hosting_server_id');} }
