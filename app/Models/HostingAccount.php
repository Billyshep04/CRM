<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HostingAccount extends Model { protected $guarded=['id']; protected $casts=['domains'=>'array','metadata'=>'array','last_synced_at'=>'datetime']; public function server(){return $this->belongsTo(HostingServer::class,'hosting_server_id');} public function customer(){return $this->belongsTo(Customer::class);} public function websites(){return $this->hasMany(Website::class);} }
