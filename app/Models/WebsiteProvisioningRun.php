<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebsiteProvisioningRun extends Model {
 protected $guarded=['id'];
 protected $attributes=['state'=>'pending','mode'=>'mock','website_type'=>'wordpress','attempts'=>0];
 protected $casts=['options'=>'array','started_at'=>'datetime','completed_at'=>'datetime'];
 public function website(){return $this->belongsTo(Website::class);} public function hostingServer(){return $this->belongsTo(HostingServer::class);} public function hostingPackage(){return $this->belongsTo(HostingPackage::class);} public function account(){return $this->belongsTo(HostingAccount::class,'hosting_account_id');} public function steps(){return $this->hasMany(WebsiteProvisioningStep::class)->orderBy('id');}
}
