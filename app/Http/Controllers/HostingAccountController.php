<?php
namespace App\Http\Controllers;
use App\Models\HostingAccount; use App\Models\HostingServer; use App\Models\Website; use App\Services\Hosting\HostingAccountSyncService; use App\Services\Hosting\HostingProviderManager; use Illuminate\Http\Request;
class HostingAccountController extends Controller {
 public function index(Request $r){return response()->json(['data'=>HostingAccount::with(['server:id,name,provider,status','customer:id,name','websites:id,name,domain,hosting_account_id'])->when($r->filled('hosting_server_id'),fn($q)=>$q->where('hosting_server_id',$r->integer('hosting_server_id')))->when($r->boolean('unassigned'),fn($q)=>$q->whereNull('customer_id'))->orderBy('username')->get()]);}
 public function sync(HostingServer $hostingServer,HostingAccountSyncService $sync){return response()->json(['data'=>$sync->sync($hostingServer)]);}
 public function update(Request $r,HostingAccount $hostingAccount){$d=$r->validate(['customer_id'=>['nullable','exists:customers,id'],'website_id'=>['nullable','exists:websites,id']]); $hostingAccount->update(['customer_id'=>$d['customer_id']??null]); if(!empty($d['website_id'])) Website::whereKey($d['website_id'])->update(['hosting_server_id'=>$hostingAccount->hosting_server_id,'hosting_account_id'=>$hostingAccount->id,'cpanel_username'=>$hostingAccount->username,'hosting_enabled'=>true]); return response()->json(['data'=>$hostingAccount->fresh(['customer','websites'])]);}
 public function session(HostingAccount $hostingAccount,HostingProviderManager $providers){$url=$providers->for($hostingAccount->server)->cpanelSession($hostingAccount->server,$hostingAccount); if(!$url)return response()->json(['message'=>'Temporary cPanel sessions are not available for this provider.'],422);return response()->json(['data'=>['url'=>$url]]);}
}
