<?php
namespace Database\Seeders;
use App\Models\WordpressProfile; use Illuminate\Database\Seeder;
class WordpressProfileSeeder extends Seeder { public function run():void { $defaults=['title_template'=>'{site_name}','admin_username'=>'webstamp_admin','permalink'=>'/%postname%/','timezone'=>'Europe/London','delete_default_content'=>true,'maintenance_defaults'=>true]; foreach([['Web Stamp Starter','starter',[...$defaults,'plugins'=>[]]],['Web Stamp Standard','standard',[...$defaults,'plugins'=>['wordpress-seo']]],['Web Stamp WooCommerce','woocommerce',[...$defaults,'plugins'=>['wordpress-seo','woocommerce'],'woocommerce'=>true]]]as[$name,$slug,$config])WordpressProfile::updateOrCreate(['slug'=>$slug],['name'=>$name,'configuration'=>$config,'active'=>true]); } }
