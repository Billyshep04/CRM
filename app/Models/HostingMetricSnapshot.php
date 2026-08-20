<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HostingMetricSnapshot extends Model { protected $guarded=['id']; protected $casts=['metrics'=>'array','captured_at'=>'datetime']; public function account(){return $this->belongsTo(HostingAccount::class,'hosting_account_id');} public function website(){return $this->belongsTo(Website::class);} }
