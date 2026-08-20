<?php

namespace App\Services\Hosting;

use App\Exceptions\ManualProvisioningActionRequired;
use App\Models\HostingAccount;
use App\Models\WebsiteActivity;
use App\Models\WebsiteCredential;
use App\Models\WebsiteHealthCheck;
use App\Models\WebsiteProvisioningRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebsiteProvisioner
{
    public function __construct(private HostingProviderManager $providers) {}

    public function process(WebsiteProvisioningRun $run): WebsiteProvisioningRun
    {
        return Cache::lock("website-provisioning:{$run->id}", 120)->block(3, function () use ($run) {
            $run = $run->fresh(['website', 'steps', 'hostingServer', 'hostingPackage', 'account']);
            if ($run->state === 'complete') return $run;
            $run->update(['started_at' => $run->started_at ?? now(), 'attempts' => $run->attempts + 1, 'completed_at' => null]);
            foreach ($run->steps as $step) {
                if ($step->status === 'complete') continue;
                try { $this->executeStep($run, $step); }
                catch (ManualProvisioningActionRequired $e) {
                    $step->update(['status'=>'manual_action','completed_at'=>now(),'safe_message'=>$e->getMessage()]);
                    $run->update(['state'=>'action_required','failed_step'=>$step->step,'safe_error'=>$e->getMessage()]);
                    $run->website->update(['provisioning_status'=>'action_required']);
                    return $run->fresh(['website','account','steps']);
                } catch (Throwable $e) {
                    $safe = $e instanceof RuntimeException ? $e->getMessage() : 'Provisioning failed. Review server logs.';
                    $step->update(['status'=>'failed','completed_at'=>now(),'safe_message'=>$safe]);
                    $run->update(['state'=>'failed','failed_step'=>$step->step,'safe_error'=>$safe]);
                    $run->website->update(['provisioning_status'=>'failed']);
                    return $run->fresh(['website','account','steps']);
                }
            }
            $run->update(['state'=>'complete','completed_at'=>now(),'failed_step'=>null,'safe_error'=>null]);
            $run->website->update(['provisioning_status'=>'complete','lifecycle_state'=>'active']);
            return $run->fresh(['website','account','steps']);
        });
    }

    private function executeStep(WebsiteProvisioningRun $run, $step): void
    {
        $step->update(['status'=>'running','started_at'=>now(),'completed_at'=>null,'attempts'=>$step->attempts+1]);
        $run->update(['state'=>$this->state($step->step)]);
        $website=$run->website; $server=$run->hostingServer; $provider=$this->providers->forMode($server,$run->mode); $result=[];
        if ($step->step === 'create_cpanel_account') {
            $this->guardLive();
            if (! $run->hosting_account_id) {
                $username=$this->username($website->domain);
                $result=$provider->createAccount($server,['username'=>$username,'domain'=>$website->domain,'password'=>Str::password(32),'package_name'=>$run->hostingPackage?->name]);
                $account=HostingAccount::updateOrCreate(['hosting_server_id'=>$server->id,'external_id'=>$result['external_id']],[...$result,'customer_id'=>$website->customer_id,'last_synced_at'=>now()]);
                $run->update(['hosting_account_id'=>$account->id]);
                $website->update(['hosting_account_id'=>$account->id,'cpanel_username'=>$account->username,'hosting_enabled'=>true]);
            }
        } elseif ($step->step === 'wait_for_cpanel') $result=$provider->verifyAccount($server,$this->account($run));
        elseif ($step->step === 'install_wordpress') {
            $username=config('hosting.wordpress_admin_username','webstamp_admin');
            if (strtolower($username)==='admin') throw new RuntimeException('The WordPress admin username must not be “admin”.');
            $password=Str::password(32);
            $result=$provider->installWordpress($server,$this->account($run),['domain'=>$website->domain,'admin_username'=>$username,'admin_password'=>$password,'admin_email'=>config('hosting.wordpress_admin_email')]);
            WebsiteCredential::updateOrCreate(['website_id'=>$website->id,'type'=>'wordpress_admin'],['username'=>$username,'secret_encrypted'=>$password,'created_by_user_id'=>$run->initiated_by_user_id,'revealed_at'=>null,'revoked_at'=>null]);
        } elseif ($step->step === 'configure_wordpress') $result=$provider->configureWordpress($server,$this->account($run),['profile_id'=>$run->wordpress_profile_id,'options'=>$run->options??[]]);
        elseif ($step->step === 'install_agent') $result=$provider->installAgent($server,$this->account($run),['domain'=>$website->domain,'agent_token'=>$website->agent_token_encrypted]);
        elseif ($step->step === 'enable_monitoring') $website->update(['monitoring_enabled'=>true,'agent_last_seen_at'=>$run->mode==='mock'?now():$website->agent_last_seen_at]);
        elseif ($step->step === 'run_initial_health_check' && $run->mode === 'mock') {
            WebsiteHealthCheck::create(['website_id'=>$website->id,'check_type'=>'provisioning_mock','uptime_status'=>'online','http_status'=>200,'overall_status'=>'healthy','checked_at'=>now(),'warnings'=>[],'errors'=>[],'metrics'=>['mock'=>true]]);
            $website->update(['status'=>'healthy','last_checked_at'=>now()]);
        }
        $step->update(['status'=>'complete','completed_at'=>now(),'safe_message'=>Str::headline($step->step).' complete.','metadata'=>$this->safeMetadata($result)]);
        WebsiteActivity::create(['website_id'=>$website->id,'created_by_user_id'=>$run->initiated_by_user_id,'type'=>'provisioning_'.$step->step,'title'=>Str::headline($step->step),'performed_at'=>now(),'visible_to_customer'=>in_array($step->step,['create_cpanel_account','install_wordpress','run_initial_health_check'],true)]);
    }

    private function account(WebsiteProvisioningRun $run): HostingAccount { return $run->account()->first() ?? throw new RuntimeException('The hosting account has not been created yet.'); }
    private function guardLive():void { if(config('hosting.provisioning_mode')==='live'&&!config('hosting.allow_live_provisioning')) throw new RuntimeException('Live hosting provisioning is disabled.'); }
    private function username(string $domain):string { $base=preg_replace('/[^a-z0-9]/','',strtolower(strtok($domain,'.'))); return substr($base?:'webstamp',0,12).Str::lower(Str::random(3)); }
    private function state(string $s):string { return ['create_cpanel_account'=>'creating_hosting','wait_for_cpanel'=>'waiting_for_hosting','install_wordpress'=>'installing_wordpress','configure_wordpress'=>'configuring_wordpress','install_agent'=>'installing_agent','enable_monitoring'=>'enabling_monitoring','run_initial_health_check'=>'running_checks'][$s]??'pending'; }
    private function safeMetadata(array $result):array { return collect($result)->except(['password','admin_password','token','agent_token','api_token'])->all(); }
}
