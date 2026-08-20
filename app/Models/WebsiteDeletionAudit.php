<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebsiteDeletionAudit extends Model { protected $guarded=['id']; protected $casts=['metadata'=>'array','requested_at'=>'datetime','completed_at'=>'datetime']; }
