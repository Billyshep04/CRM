<?php
namespace App\Services\Websites;

use App\Models\Website;
use App\Models\WebsiteDeletionAudit;
use App\Services\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebsiteDeletionService
{
    public function __construct(private HostingProviderManager $providers) {}

    public function preview(Website $website): array
    {
        $website->load(['customer','hostingServer','hostingAccount.websites','latestHealthCheck']); $account=$website->hostingAccount; $reasons=[];
        if (!$account) $reasons[]='No mapped hosting account.';
        if (!$website->hostingServer) $reasons[]='No mapped hosting server.';
        if ($account && $account->hosting_server_id !== $website->hosting_server_id) $reasons[]='Hosting server mapping does not match.';
        if ($account && $account->customer_id && $account->customer_id !== $website->customer_id) $reasons[]='Hosting account belongs to a different customer.';
        if ($account && $account->websites->where('id','!=',$website->id)->isNotEmpty()) $reasons[]='Hosting account is shared by another CRM website.';
        $expected=$this->domain($website->domain); $domains=collect($account?->domains??[])->push($account?->primary_domain)->map(fn($d)=>$this->domain(is_array($d)?($d['domain']??''):$d))->filter()->unique();
        if ($account && $expected && $domains->isNotEmpty() && !$domains->contains($expected)) $reasons[]='Website domain is not present on the hosting account.';
        if ($account && $domains->filter(fn($d)=>$d!==$expected)->isNotEmpty()) $reasons[]='Hosting account contains other domains and cannot be safely terminated.';
        return ['website_id'=>$website->id,'name'=>$website->name,'domain'=>$website->domain,'customer'=>$website->customer?->name,'hosting_provider'=>$website->hostingServer?->provider,'hosting_server'=>$website->hostingServer?->name,'cpanel_username'=>$account?->username,'hosting_termination_allowed'=>$reasons===[],'blocking_reasons'=>$reasons,'latest_known_backup_at'=>$website->latestHealthCheck?->last_successful_backup_at,'backup_status'=>$website->latestHealthCheck?->backup_status,'backup_warning'=>'A known backup is not proof that a terminated account can be restored. Verify recovery separately before deletion.'];
    }

    public function delete(Website $website,string $type,string $confirmation,string $key,?int $userId,bool $backupConfirmed=false): WebsiteDeletionAudit
    {
        if (!hash_equals(strtolower(trim((string)$website->domain)),strtolower(trim($confirmation)))) throw new RuntimeException('Type the website domain exactly to confirm deletion.');
        if ($type==='hosting_and_crm' && !$backupConfirmed) throw new RuntimeException('Review and confirm the backup warning before deleting hosting.');
        return Cache::lock("website-deletion:{$website->id}",120)->block(3,function()use($website,$type,$key,$userId){
            if ($existing=WebsiteDeletionAudit::where('idempotency_key',$key)->first()) return $existing;
            $preview=$this->preview($website); $account=$website->hostingAccount; $server=$website->hostingServer;
            if ($type==='hosting_and_crm' && !$preview['hosting_termination_allowed']) throw new RuntimeException(implode(' ',$preview['blocking_reasons']));
            if ($type==='hosting_and_crm' && config('hosting.termination_mode')==='live' && (!config('hosting.allow_live_termination') || !app()->environment('production'))) throw new RuntimeException('Live hosting termination is disabled outside the explicitly enabled production environment.');
            $audit=WebsiteDeletionAudit::create(['idempotency_key'=>$key,'website_id'=>$website->id,'customer_id'=>$website->customer_id,'hosting_server_id'=>$website->hosting_server_id,'hosting_account_id'=>$website->hosting_account_id,'initiated_by_user_id'=>$userId,'website_name'=>$website->name,'domain'=>$website->domain,'customer_name'=>$website->customer?->name,'hosting_provider'=>$server?->provider,'hosting_server_name'=>$server?->name,'cpanel_username'=>$account?->username,'deletion_type'=>$type,'state'=>'requested','metadata'=>['mode'=>$type==='hosting_and_crm'?config('hosting.termination_mode'):'crm_only'],'requested_at'=>now()]);
            $website->update(['lifecycle_state'=>'deletion_requested','deletion_status'=>'processing']);
            try {
                $providerResult=[];
                if ($type==='hosting_and_crm') { $audit->update(['state'=>'terminating_hosting']); $website->update(['deletion_status'=>'terminating_hosting']); $providerResult=$this->providers->forMode($server,config('hosting.termination_mode','mock'))->terminateAccount($server,$account); }
                $audit->update(['state'=>'removing_crm']); $website->update(['deletion_status'=>'removing_crm']);
                $website->credentials()->update(['revoked_at'=>now()]);
                $website->provisioningRuns()->whereNotIn('state',['complete','failed'])->update(['state'=>'failed','failed_step'=>'website_deleted','safe_error'=>'Provisioning stopped because the CRM website was deleted.','next_check_at'=>null,'completed_at'=>now()]);
                $website->update(['agent_token_hash'=>null,'agent_token_encrypted'=>null,'monitoring_enabled'=>false,'deletion_status'=>'complete']);
                $website->delete();
                if ($type==='hosting_and_crm') $account->delete();
                $audit->update(['state'=>'complete','completed_at'=>now(),'metadata'=>[...($audit->metadata??[]),'provider_result'=>collect($providerResult)->except(['token','password','api_token'])->all()]]);
            } catch (Throwable $e) {
                $safe=$e instanceof RuntimeException?$e->getMessage():'Deletion failed. Review server logs.';
                $website->update(['lifecycle_state'=>'active','deletion_status'=>'failed']); $audit->update(['state'=>'failed','safe_error'=>$safe]); throw new RuntimeException($safe);
            }
            return $audit->fresh();
        });
    }
    private function domain(?string $value):string { $value=strtolower(trim((string)$value)); $host=parse_url(str_contains($value,'://')?$value:'https://'.$value,PHP_URL_HOST); return rtrim((string)($host?:$value),'.'); }
}
