<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WordpressProfile extends Model { protected $guarded=['id']; protected $casts=['configuration'=>'array','active'=>'boolean']; }
