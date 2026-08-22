<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebsiteProvisioningRun extends Model {
 protected $guarded=['id'];
 protected $hidden=['secrets_encrypted'];
 protected $attributes=['state'=>'pending','mode'=>'mock','website_type'=>'wordpress','attempts'=>0];
 protected $casts=['options'=>'array','secrets_encrypted'=>'encrypted:array','dns_status'=>'array','ssl_status'=>'array','next_check_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime'];
 public function website(){return $this->belongsTo(Website::class);} public function hostingServer(){return $this->belongsTo(HostingServer::class);} public function hostingPackage(){return $this->belongsTo(HostingPackage::class);} public function wordpressProfile(){return $this->belongsTo(WordpressProfile::class);} public function account(){return $this->belongsTo(HostingAccount::class,'hosting_account_id');} public function steps(){return $this->hasMany(WebsiteProvisioningStep::class)->orderBy('id');}
}
