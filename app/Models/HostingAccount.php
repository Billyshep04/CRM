<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HostingAccount extends Model { protected $guarded=['id']; protected $casts=['domains'=>'array','metadata'=>'array','resource_limits'=>'array','dns'=>'array','last_synced_at'=>'datetime','provider_created_at'=>'datetime','last_metrics_synced_at'=>'datetime','ssl_expires_at'=>'datetime']; public function server(){return $this->belongsTo(HostingServer::class,'hosting_server_id');} public function customer(){return $this->belongsTo(Customer::class);} public function websites(){return $this->hasMany(Website::class);} public function metricSnapshots(){return $this->hasMany(HostingMetricSnapshot::class)->latest('captured_at');} }
