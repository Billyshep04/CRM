<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebsiteCredential extends Model { protected $guarded=['id']; protected $casts=['secret_encrypted'=>'encrypted','revealed_at'=>'datetime','revoked_at'=>'datetime']; protected $hidden=['secret_encrypted']; public function website(){return $this->belongsTo(Website::class);} }
