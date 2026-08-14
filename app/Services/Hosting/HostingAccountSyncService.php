<?php
namespace App\Services\Hosting;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
class HostingAccountSyncService {
 public function __construct(private HostingProviderManager $providers){}
 public function sync(HostingServer $server): array { $provider=$this->providers->for($server); $accounts=collect($provider->accounts($server))->map(function($a)use($server){$account=HostingAccount::updateOrCreate(['hosting_server_id'=>$server->id,'external_id'=>$a['external_id']], [...$a,'last_synced_at'=>now()]); if(!$account->customer_id&&$account->primary_domain){$site=\App\Models\Website::where('domain',$account->primary_domain)->first(); if($site){$account->update(['customer_id'=>$site->customer_id]); if(!$site->hosting_account_id)$site->update(['hosting_account_id'=>$account->id]);}} return $account;}); $packages=collect($provider->packages($server))->map(fn($p)=>HostingPackage::updateOrCreate(['hosting_server_id'=>$server->id,'external_id'=>$p['external_id']],[...$p,'last_synced_at'=>now(),'active'=>true])); return ['accounts'=>$accounts->count(),'packages'=>$packages->count()]; }
}
