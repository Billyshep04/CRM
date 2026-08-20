<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessWebsiteProvisioning; use App\Models\HostingServer; use App\Models\Website; use App\Models\WebsiteProvisioningRun; use App\Models\WordpressProfile; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Support\Str; use Illuminate\Validation\ValidationException;
class WebsiteProvisioningController extends Controller {
 public function options(){return response()->json(['data'=>['mode'=>config('hosting.provisioning_mode','mock'),'live_enabled'=>config('hosting.allow_live_provisioning',false),'servers'=>HostingServer::with(['packages'=>fn($q)=>$q->where('active',true)])->where('status','active')->get()->map(fn($server)=>[...$server->toArray(),'credential_username'=>$server->credentials['username']??null,'has_token'=>!empty($server->credentials['token'])]),'profiles'=>WordpressProfile::where('active',true)->get()]]);}
 public function store(Request $request){
  $d=$request->validate(['customer_id'=>['required','exists:customers,id'],'name'=>['required','string','max:255'],'domain'=>['required','string','max:255'],'environment'=>['required','in:production,development,staging'],'hosting_server_id'=>['required','exists:hosting_servers,id'],'hosting_package_id'=>['required','exists:hosting_packages,id'],'wordpress_profile_id'=>['nullable','exists:wordpress_profiles,id'],'website_type'=>['required','in:wordpress,blank'],'options'=>['array'],'idempotency_key'=>['required','string','max:100']]);
  if(config('hosting.provisioning_mode')==='live'&&!config('hosting.allow_live_provisioning'))throw ValidationException::withMessages(['provisioning'=>['Live hosting provisioning is disabled.']]);
  $d['domain']=strtolower(trim($d['domain']));
  $run=DB::transaction(function()use($d,$request){
   if($existing=WebsiteProvisioningRun::where('idempotency_key',$d['idempotency_key'])->lockForUpdate()->first())return$existing;
   if(Website::where('domain',$d['domain'])->exists()||WebsiteProvisioningRun::where('domain',$d['domain'])->exists())throw ValidationException::withMessages(['domain'=>['This domain already exists or is already being provisioned.']]);
   $token=Str::random(64);$website=Website::create(['customer_id'=>$d['customer_id'],'hosting_server_id'=>$d['hosting_server_id'],'name'=>$d['name'],'domain'=>$d['domain'],'login_url'=>'https://'.$d['domain'],'environment'=>$d['environment'],'wordpress_enabled'=>$d['website_type']==='wordpress','management_enabled'=>true,'hosting_enabled'=>true,'provisioning_status'=>'pending','status'=>'unknown','portal_visibility'=>Website::defaultPortalVisibility(),'agent_token_hash'=>hash('sha256',$token),'agent_token_encrypted'=>$token]);
   $run=WebsiteProvisioningRun::create(['public_id'=>(string)Str::uuid(),'website_id'=>$website->id,'hosting_server_id'=>$d['hosting_server_id'],'hosting_package_id'=>$d['hosting_package_id'],'wordpress_profile_id'=>$d['wordpress_profile_id']??null,'initiated_by_user_id'=>$request->user()->id,'idempotency_key'=>$d['idempotency_key'],'domain'=>$d['domain'],'mode'=>config('hosting.provisioning_mode','mock'),'website_type'=>$d['website_type'],'options'=>$d['options']??[]]);
   foreach(['create_cpanel_account','wait_for_cpanel',...($d['website_type']==='wordpress'?['install_wordpress','configure_wordpress','install_agent','enable_monitoring']:[]),'run_initial_health_check']as$step)$run->steps()->create(['step'=>$step]);return$run;
  });
  if($run->state==='pending')ProcessWebsiteProvisioning::dispatch($run->id)->afterCommit();
  return response()->json(['data'=>$run->fresh(['website','account','steps'])],201);
 }
 public function show(WebsiteProvisioningRun $websiteProvisioningRun){return response()->json(['data'=>$websiteProvisioningRun->load(['website','account','steps'])]);}
 public function retry(WebsiteProvisioningRun $websiteProvisioningRun){if(!in_array($websiteProvisioningRun->state,['failed','action_required'],true))throw ValidationException::withMessages(['state'=>['Only failed or action-required provisioning runs can be retried.']]);$websiteProvisioningRun->steps()->whereIn('status',['failed','manual_action'])->update(['status'=>'pending','safe_message'=>null,'completed_at'=>null]);ProcessWebsiteProvisioning::dispatch($websiteProvisioningRun->id);return response()->json(['data'=>$websiteProvisioningRun->fresh(['steps','account'])],202);}
}
