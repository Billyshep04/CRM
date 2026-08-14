<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebsiteProvisioningStep extends Model { protected $guarded=['id']; protected $casts=['metadata'=>'array','started_at'=>'datetime','completed_at'=>'datetime']; }
