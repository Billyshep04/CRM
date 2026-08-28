<?php
namespace Tests\Feature;
use App\Models\Customer; use App\Models\HostingAccount; use App\Models\HostingPackage; use App\Models\HostingServer; use App\Models\Permission; use App\Models\Role; use App\Models\User; use App\Services\Hosting\WhmCpanelUapiClient; use Database\Seeders\RolePermissionSeeder; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\Http; use Illuminate\Support\Facades\Log; use Illuminate\Support\Facades\Queue; use Illuminate\Support\Facades\Schema; use Tests\TestCase;
class HostingResellerProvisioningTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(RolePermissionSeeder::class);}
 public function test_credentials_are_encrypted_hidden_and_mock_connection_tests():void{$admin=$this->user('admin');$server=HostingServer::create(['name'=>'Krystal Trinity','provider'=>'krystal','api_type'=>'mock','credentials'=>['username'=>'reseller','token'=>'secret-token']]);$this->assertStringNotContainsString('secret-token',(string)$server->getRawOriginal('credentials'));$this->actingAs($admin)->getJson('/api/hosting-servers')->assertOk()->assertJsonMissing(['token'=>'secret-token'])->assertJsonPath('data.0.has_token',true);$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/test")->assertOk()->assertJsonPath('data.ok',true);}
 public function test_permission_migration_repairs_existing_admin_role_assignments():void{$adminRole=Role::where('slug','admin')->firstOrFail();$slugs=['hosting_view','hosting_manage','hosting_provision','hosting_credentials','hosting_terminate'];$permissionIds=Permission::whereIn('slug',$slugs)->pluck('id');$adminRole->permissions()->detach($permissionIds);Permission::whereIn('slug',$slugs)->delete();$migration=require database_path('migrations/2026_08_16_000000_ensure_hosting_permissions_for_admin_role.php');$migration->up();$this->assertSame(5,Permission::whereIn('slug',$slugs)->count());$this->assertSame(5,$adminRole->permissions()->whereIn('slug',$slugs)->count());$admin=$this->user('admin');$this->actingAs($admin)->getJson('/api/website-provisioning/options')->assertOk();}
 public function test_reseller_migration_resumes_after_a_partial_mysql_application():void{Schema::disableForeignKeyConstraints();Schema::dropIfExists('website_provisioning_steps');Schema::dropIfExists('website_provisioning_runs');Schema::dropIfExists('wordpress_profiles');Schema::dropIfExists('hosting_packages');Schema::enableForeignKeyConstraints();$this->assertTrue(Schema::hasTable('hosting_accounts'));$migration=require database_path('migrations/2026_08_15_010000_create_reseller_hosting_and_provisioning_tables.php');$migration->up();foreach(['hosting_accounts','hosting_packages','wordpress_profiles','website_provisioning_runs','website_provisioning_steps']as$table)$this->assertTrue(Schema::hasTable($table));$this->assertTrue(Schema::hasColumn('websites','hosting_account_id'));$this->assertTrue(Schema::hasColumn('websites','provisioning_status'));}
 public function test_admin_can_access_all_hosting_setup_reads_and_actions_without_exposing_token():void{$admin=$this->user('admin');$customer=$this->customer();$server=HostingServer::create(['name'=>'Krystal Trinity','provider'=>'krystal','api_type'=>'mock','credentials'=>['username'=>'reseller','token'=>'never-return-this-token'],'metadata'=>['mock_accounts'=>[['external_id'=>'clienta','username'=>'clienta','primary_domain'=>'client.test','status'=>'active']]]]);$options=$this->actingAs($admin)->getJson('/api/website-provisioning/options')->assertOk()->assertJsonPath('data.servers.0.id',$server->id)->assertJsonPath('data.servers.0.credential_username','reseller')->assertJsonPath('data.servers.0.has_token',true);$this->assertStringNotContainsString('never-return-this-token',$options->getContent());$accounts=$this->actingAs($admin)->getJson('/api/website-provisioning/hosting-accounts')->assertOk();$this->assertStringNotContainsString('never-return-this-token',$accounts->getContent());$servers=$this->actingAs($admin)->getJson('/api/hosting-servers')->assertOk()->assertJsonPath('data.0.credential_username','reseller')->assertJsonPath('data.0.has_token',true);$this->assertStringNotContainsString('never-return-this-token',$servers->getContent());$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/test")->assertOk();$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk()->assertJsonPath('data.accounts',1);$this->actingAs($admin)->getJson('/api/website-provisioning/hosting-accounts')->assertOk()->assertJsonPath('data.0.username','clienta');}
 public function test_non_admins_remain_forbidden_from_all_hosting_setup_endpoints():void{$customerUser=$this->user('customer');$staff=$this->user('staff');$server=HostingServer::create(['name'=>'Mock','api_type'=>'mock']);foreach([$customerUser,$staff]as$user){$this->actingAs($user)->getJson('/api/website-provisioning/options')->assertForbidden();$this->actingAs($user)->getJson('/api/website-provisioning/hosting-accounts')->assertForbidden();$this->actingAs($user)->getJson('/api/hosting-servers')->assertForbidden();$this->actingAs($user)->postJson("/api/hosting-servers/{$server->id}/test")->assertForbidden();$this->actingAs($user)->postJson("/api/hosting-servers/{$server->id}/sync")->assertForbidden();}}
 public function test_admin_can_save_ssh_fingerprint_without_erasing_other_server_metadata():void{$admin=$this->user('admin');$server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret'],'metadata'=>['existing_setting'=>'kept']]);$fingerprint='SHA256:'.str_repeat('a',43);$this->actingAs($admin)->putJson("/api/hosting-servers/{$server->id}",['metadata'=>['ssh_host_fingerprint'=>$fingerprint]])->assertOk();$server->refresh();$this->assertSame('kept',$server->metadata['existing_setting']);$this->assertSame($fingerprint,$server->metadata['ssh_host_fingerprint']);$this->actingAs($admin)->getJson('/api/website-provisioning/options')->assertOk()->assertJsonPath('data.servers.0.ssh_host_fingerprint',$fingerprint);}
 public function test_account_sync_is_idempotent_and_maps_to_customer_and_website():void{$admin=$this->user('admin');$customer=$this->customer();$server=HostingServer::create(['name'=>'Mock','api_type'=>'mock','metadata'=>['mock_accounts'=>[['external_id'=>'clienta','username'=>'clienta','primary_domain'=>'client.test','status'=>'active']]]]);$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk();$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk();$this->assertSame(1,HostingAccount::count());$website=\App\Models\Website::create(['customer_id'=>$customer->id,'name'=>'Client','domain'=>'client.test','login_url'=>'https://client.test']);$account=HostingAccount::first();$this->actingAs($admin)->putJson("/api/hosting-accounts/{$account->id}",['customer_id'=>$customer->id,'website_id'=>$website->id])->assertOk();$this->assertSame($account->id,$website->fresh()->hosting_account_id);}
 public function test_whm_adapter_tests_and_syncs_without_exposing_token():void{Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'clienta','domain'=>'client-a.test','plan'=>'Standard','suspended'=>0]]]]),'https://whm.example.test:2087/json-api/listpkgs*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['pkg'=>[['name'=>'Standard','QUOTA'=>'5000']]]])]);$admin=$this->user('admin');$server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'whm-secret']]);$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/test")->assertOk();$this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk()->assertJsonPath('data.accounts',1)->assertJsonPath('data.packages',1);$this->assertDatabaseHas('hosting_accounts',['username'=>'clienta']);$this->actingAs($admin)->getJson('/api/hosting-servers')->assertJsonMissing(['token'=>'whm-secret']);}
 public function test_whm_uapi_database_creation_accepts_success_with_null_data():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/uapi_cpanel*'=>Http::response(['metadata'=>['reason'=>'OK','command'=>'uapi_cpanel','result'=>1,'version'=>1],'data'=>['uapi'=>['status'=>1,'messages'=>null,'errors'=>null,'metadata'=>[],'warnings'=>null,'data'=>null]]])]);
  [$server,$account]=$this->whmUapiAccount();
  $result=app(WhmCpanelUapiClient::class)->call($server,$account,'Mysql','create_database',['name'=>'wp']);
  $this->assertSame(1,$result['result']['status']);
  $this->assertNull($result['result']['data']);
  Http::assertSent(fn($request)=>str_contains($request->url(),'/json-api/uapi_cpanel')&&$request['api.version']===1&&$request['cpanel.user']==='newclient'&&$request['cpanel.module']==='Mysql'&&$request['cpanel.function']==='create_database'&&$request['name']==='wp');
 }
 public function test_whm_uapi_malformed_response_is_rejected_with_structural_logging_only():void
 {
  Log::spy();
  Http::fake(['https://whm.example.test:2087/json-api/uapi_cpanel*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['unexpected'=>true]])]);
  [$server,$account]=$this->whmUapiAccount();
  try{app(WhmCpanelUapiClient::class)->call($server,$account,'Mysql','create_database',['name'=>'wp']);$this->fail('Expected malformed response failure.');}
  catch(\RuntimeException $exception){$this->assertSame('WHM returned no cPanel database result for this account.',$exception->getMessage());}
  Log::shouldHaveReceived('warning')->once()->withArgs(function($message,$context){return $message==='WHM returned a malformed uapi_cpanel response.'&&$context===['top_level_keys'=>['metadata','data'],'metadata_result'=>1,'has_data'=>true,'has_uapi'=>false,'has_result'=>false];});
 }
 public function test_whm_uapi_status_zero_surfaces_sanitized_errors_and_messages():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/uapi_cpanel*'=>Http::response(['metadata'=>['reason'=>'OK','command'=>'uapi_cpanel','result'=>1,'version'=>1],'data'=>['uapi'=>['status'=>0,'messages'=>['Creation was refused.'],'errors'=>['Database quota reached.'],'metadata'=>[],'warnings'=>null,'data'=>null]]])]);
  [$server,$account]=$this->whmUapiAccount();
  try{app(WhmCpanelUapiClient::class)->call($server,$account,'Mysql','create_database',['name'=>'wp']);$this->fail('Expected UAPI status failure.');}
  catch(\RuntimeException $exception){$this->assertSame('The cPanel UAPI database operation failed: Database quota reached. Creation was refused.',$exception->getMessage());}
 }
 public function test_whm_uapi_failure_redacts_sensitive_function_arguments():void
 {
  $databasePassword='do-not-expose-this-password';
  Http::fake(['https://whm.example.test:2087/json-api/uapi_cpanel*'=>Http::response(['metadata'=>['reason'=>'OK','command'=>'uapi_cpanel','result'=>1,'version'=>1],'data'=>['uapi'=>['status'=>0,'messages'=>null,'errors'=>["Rejected password {$databasePassword}."],'metadata'=>[],'warnings'=>null,'data'=>null]]])]);
  [$server,$account]=$this->whmUapiAccount();
  try{app(WhmCpanelUapiClient::class)->call($server,$account,'Mysql','create_user',['name'=>'wpuser','password'=>$databasePassword]);$this->fail('Expected UAPI status failure.');}
  catch(\RuntimeException $exception){$this->assertStringContainsString('[REDACTED]',$exception->getMessage());$this->assertStringNotContainsString($databasePassword,$exception->getMessage());}
 }
 public function test_mock_provisioning_is_idempotent_and_duplicate_domain_is_blocked():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$payload=$this->payload($customer,$server,$package);$first=$this->actingAs($admin)->postJson('/api/website-provisioning',$payload)->assertCreated()->assertJsonPath('data.state','complete');$this->actingAs($admin)->postJson('/api/website-provisioning',$payload)->assertCreated()->assertJsonPath('data.id',$first->json('data.id'));$this->assertDatabaseCount('websites',1);$this->assertDatabaseCount('hosting_accounts',1);$this->actingAs($admin)->postJson('/api/website-provisioning',[...$payload,'idempotency_key'=>'another-request'])->assertUnprocessable()->assertJsonValidationErrors('domain');}
 public function test_deleted_domain_can_be_provisioned_again_without_losing_history():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$payload=$this->payload($customer,$server,$package,'copperingots.uk');$first=$this->actingAs($admin)->postJson('/api/website-provisioning',$payload)->assertCreated();$websiteId=$first->json('data.website.id');$this->actingAs($admin)->postJson("/api/websites/{$websiteId}/delete",['deletion_type'=>'crm_only','confirmation'=>'copperingots.uk','idempotency_key'=>(string)\Illuminate\Support\Str::uuid()])->assertOk();$second=$this->actingAs($admin)->postJson('/api/website-provisioning',[...$payload,'idempotency_key'=>'copperingots-second-attempt'])->assertCreated();$this->assertNotSame($first->json('data.id'),$second->json('data.id'));$this->assertDatabaseCount('website_provisioning_runs',2);$this->assertSame(2,\App\Models\Website::withTrashed()->where('domain','copperingots.uk')->count());}
 public function test_mock_preview_never_reports_verified_hosting_or_monitoring():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'preview.test'))->assertCreated();$websiteId=$response->json('data.website.id');$this->assertDatabaseHas('websites',['id'=>$websiteId,'provisioning_status'=>'preview_complete','agent_last_seen_at'=>null]);$this->assertNull(HostingAccount::firstOrFail()->last_synced_at);$this->actingAs($admin)->getJson("/api/websites/{$websiteId}")->assertOk()->assertJsonPath('data.hosting_connected',false)->assertJsonPath('data.agent_connected',false);}
 public function test_production_rejects_mock_provisioning_instead_of_claiming_success():void{$original=$this->app['env'];$this->app['env']='production';config(['hosting.provisioning_mode'=>'mock']);try{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'never-fake.test'))->assertUnprocessable()->assertJsonValidationErrors('provisioning');$this->assertDatabaseCount('websites',0);}finally{$this->app['env']=$original;}}
 public function test_whm_verification_requires_the_exact_subdomain_and_assigned_ip():void{Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'dev4site','domain'=>'other.web-stamp.co.uk','ip'=>'1.2.3.4','suspended'=>0]]]])]);$server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret']]);$account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'dev4site','username'=>'dev4site','primary_domain'=>'dev4.web-stamp.co.uk','status'=>'pending']);$this->expectException(\RuntimeException::class);$this->expectExceptionMessage('not visible in WHM');app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAccount($server,$account);}

 public function test_whm_package_shell_access_is_refreshed_and_package_controls_account_creation():void
 {
  $created=false;
  Http::fake(function($request)use(&$created){if(str_contains($request->url(),'/listpkgs'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['pkg'=>[['name'=>'Standard','HASSHELL'=>'1']]]]);if(str_contains($request->url(),'/listaccts'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>$created?[['user'=>'newsite','domain'=>'newsite.test','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]:[]]]);if(str_contains($request->url(),'/createacct')){$created=true;return Http::response(['metadata'=>['result'=>1,'reason'=>'Account created'],'data'=>['ip'=>'1.2.3.4']]);}return Http::response([],404);});
  $server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret'],'metadata'=>['ssh_host_fingerprint'=>str_repeat('a',64)]]);
  $provider=app(\App\Services\Hosting\KrystalWhmProvider::class);
  $this->assertTrue($provider->packages($server)[0]['shell_access']);
  $provider->createAccount($server,['username'=>'newsite','domain'=>'newsite.test','password'=>'not-logged','package_name'=>'Standard','shell_access'=>true]);
  Http::assertSent(fn($request)=>str_contains($request->url(),'/createacct')&&$request['plan']==='Standard'&&!array_key_exists('hasshell',$request->data()));
 }
 public function test_whm_confirms_existing_jailed_shell_without_modifying_account():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/accountsummary*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'newclient','shell'=>'/usr/local/cpanel/bin/jailshell']]]])]);
  [$server,$account]=$this->whmUapiAccount();
  $result=app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureJailedShell($server,$account);
  $this->assertSame('/usr/local/cpanel/bin/jailshell',$result['shell']);$this->assertFalse($result['changed']);
  Http::assertNotSent(fn($request)=>str_contains($request->url(),'/modifyacct'));
 }
 public function test_whm_changes_noshell_to_jailed_shell_and_confirms_it():void
 {
  $summaryCalls=0;
  Http::fake(function($request)use(&$summaryCalls){if(str_contains($request->url(),'/accountsummary')){$summaryCalls++;$shell=$summaryCalls===1?'/usr/local/cpanel/bin/noshell':'/usr/local/cpanel/bin/jailshell';return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'newclient','shell'=>$shell]]]]);}if(str_contains($request->url(),'/modifyacct'))return Http::response(['metadata'=>['result'=>1,'reason'=>'Account modified']]);return Http::response([],404);});
  [$server,$account]=$this->whmUapiAccount();
  $result=app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureJailedShell($server,$account);
  $this->assertTrue($result['changed']);$this->assertSame('/usr/local/cpanel/bin/jailshell',$result['shell']);$this->assertSame(2,$summaryCalls);
  Http::assertSent(fn($request)=>str_contains($request->url(),'/modifyacct')&&$request['user']==='newclient'&&$request['HASSHELL']===1&&$request['shell']==='/usr/local/cpanel/bin/jailshell');
 }
 public function test_whm_shell_permission_denial_returns_actionable_error_without_secrets():void
 {
  $token='never-log-shell-token';Log::spy();
  Http::fake(['https://whm.example.test:2087/json-api/accountsummary*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'newclient','shell'=>'/usr/local/cpanel/bin/noshell']]]]),'https://whm.example.test:2087/json-api/modifyacct*'=>Http::response(['metadata'=>['result'=>0,'reason'=>'Permission denied']])]);
  $server=$this->whmServer($token);$account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'newclient','username'=>'newclient','primary_domain'=>'newclient.test','status'=>'active']);
  try{app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureJailedShell($server,$account);$this->fail('Expected shell permission failure.');}catch(\RuntimeException $exception){$this->assertStringContainsString('Jailed shell is required',$exception->getMessage());$this->assertStringContainsString('ask Krystal',$exception->getMessage());$this->assertStringNotContainsString($token,$exception->getMessage());}
  Log::shouldHaveReceived('warning')->withArgs(fn($message,$context)=>$message==='WHM jailed shell assignment denied.'&&!str_contains(json_encode($context),$token));
 }
 public function test_whm_account_creation_reconciles_an_account_created_after_an_earlier_timeout():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'recovered1','domain'=>'recovered.test','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]]])]);
  $server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret'],'metadata'=>['ssh_host_fingerprint'=>str_repeat('a',64)]]);
  $result=app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($server,['username'=>'recovered1','domain'=>'recovered.test','password'=>'not-logged','package_name'=>'Standard','shell_access'=>true,'retrying'=>true]);
  $this->assertSame('recovered1',$result['username']);
  $this->assertTrue($result['metadata']['reconciled_provisioning_retry']);
  Http::assertNotSent(fn($request)=>str_contains($request->url(),'/createacct'));
 }
 public function test_whm_createacct_requires_explicit_metadata_success():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[]]]),'https://whm.example.test:2087/json-api/createacct*'=>Http::response(['metadata'=>['result'=>0,'reason'=>'Account creation refused.']])]);
  $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('Account creation refused.');
  app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($this->whmServer(),['username'=>'proposed1','domain'=>'strict.test','password'=>'private-password','package_name'=>'Standard']);
 }
 public function test_whm_createacct_rejects_missing_metadata_result():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[]]]),'https://whm.example.test:2087/json-api/createacct*'=>Http::response(['metadata'=>['reason'=>'Missing result.']])]);
  $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('Missing result.');
  app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($this->whmServer(),['username'=>'proposed1','domain'=>'missing-result.test','password'=>'private-password','package_name'=>'Standard']);
 }
 public function test_authoritative_whm_username_is_persisted_only_after_reconciliation():void
 {
  config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>true]);
  $proposed=null;$created=false;
  Http::fake(function($request)use(&$proposed,&$created){if(str_contains($request->url(),'/listaccts'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>$created?[['user'=>'authoritative1','domain'=>'authoritative.test','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]:[]]]);if(str_contains($request->url(),'/createacct')){$proposed=$request['username'];$created=true;return Http::response(['metadata'=>['result'=>1,'reason'=>'Account created']]);}return Http::response([],404);});
  [$run,$website]=$this->singleStepLiveRun('authoritative.test');
  app(\App\Services\Hosting\WebsiteProvisioner::class)->process($run);
  $this->assertNotNull($proposed);$this->assertNotSame('authoritative1',$proposed);
  $this->assertDatabaseHas('hosting_accounts',['username'=>'authoritative1','external_id'=>'authoritative1','primary_domain'=>'authoritative.test']);
  $this->assertSame('authoritative1',$website->fresh()->cpanel_username);
  $this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'create_cpanel_account','status'=>'complete']);
 }
 public function test_create_cpanel_step_does_not_complete_when_reconciliation_cannot_confirm_account():void
 {
  config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>true]);
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[]]]),'https://whm.example.test:2087/json-api/createacct*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'Account created']])]);
  [$run,$website]=$this->singleStepLiveRun('unconfirmed.test');
  app(\App\Services\Hosting\WebsiteProvisioner::class)->process($run);
  $this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'create_cpanel_account','status'=>'failed']);
  $this->assertDatabaseHas('website_provisioning_runs',['id'=>$run->id,'failed_step'=>'create_cpanel_account']);
  $this->assertDatabaseCount('hosting_accounts',0);$this->assertNull($website->fresh()->cpanel_username);
 }
 public function test_existing_domain_under_another_account_stops_without_createacct():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'wptest','domain'=>'copperingots.uk','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]]])]);
  try{app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($this->whmServer(),['username'=>'copperingutm','domain'=>'copperingots.uk','password'=>'private-password','package_name'=>'Standard']);$this->fail('Expected existing-domain conflict.');}catch(\RuntimeException $exception){$this->assertSame('This domain already exists on Krystal under cPanel account "wptest". Link the existing hosting account explicitly or use another domain.',$exception->getMessage());}
  Http::assertNotSent(fn($request)=>str_contains($request->url(),'/createacct'));
 }
 public function test_retry_reconciles_same_proposed_account_without_duplicate_createacct():void
 {
  $listCalls=0;$createCalls=0;
  Http::fake(function($request)use(&$listCalls,&$createCalls){if(str_contains($request->url(),'/listaccts')){$listCalls++;$accounts=$listCalls>=3?[['user'=>'retryname1','domain'=>'retry-safe.test','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]:[];return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>$accounts]]);}if(str_contains($request->url(),'/createacct')){$createCalls++;return Http::response(['metadata'=>['result'=>1,'reason'=>'Account created']]);}return Http::response([],404);});
  $provider=app(\App\Services\Hosting\KrystalWhmProvider::class);$server=$this->whmServer();$data=['username'=>'retryname1','domain'=>'retry-safe.test','password'=>'private-password','package_name'=>'Standard'];
  try{$provider->createAccount($server,$data);$this->fail('Expected delayed reconciliation.');}catch(\RuntimeException $exception){$this->assertStringContainsString('not visible yet',$exception->getMessage());}
  $result=$provider->createAccount($server,[...$data,'retrying'=>true]);
  $this->assertSame('retryname1',$result['username']);$this->assertSame(1,$createCalls);
 }
 public function test_malformed_listaccts_response_stops_before_account_creation():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>[]])]);
  $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('invalid account list');
  app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($this->whmServer(),['username'=>'proposed1','domain'=>'malformed.test','password'=>'private-password','package_name'=>'Standard']);
 }
 public function test_verify_account_returns_authoritative_domain_identity_without_mutating_stored_username():void
 {
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'wptest','domain'=>'copperingots.uk','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]]])]);
  $server=$this->whmServer();$account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'copperingutm','username'=>'copperingutm','primary_domain'=>'copperingots.uk','status'=>'pending']);
  $result=app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAccount($server,$account);
  $this->assertSame('wptest',$result['username']);$this->assertFalse($result['username_matches_stored']);$this->assertSame('copperingutm',$account->fresh()->username);
 }
 public function test_createacct_failure_logging_redacts_credentials():void
 {
  Log::spy();$password='do-not-log-password';$token='do-not-log-token';
  Http::fake(['https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[]]]),'https://whm.example.test:2087/json-api/createacct*'=>Http::response(['metadata'=>['result'=>0,'reason'=>"Rejected {$password} using {$token}"]])]);
  $server=$this->whmServer($token);
  try{app(\App\Services\Hosting\KrystalWhmProvider::class)->createAccount($server,['username'=>'safeuser1','domain'=>'safe-log.test','password'=>$password,'package_name'=>'Standard']);$this->fail('Expected createacct failure.');}catch(\RuntimeException $exception){$this->assertStringNotContainsString($password,$exception->getMessage());$this->assertStringNotContainsString($token,$exception->getMessage());}
  Log::shouldHaveReceived('warning')->once()->withArgs(function($message,$context)use($password,$token){$encoded=json_encode($context);return$message==='WHM createacct failed.'&&!str_contains($encoded,$password)&&!str_contains($encoded,$token)&&str_contains($encoded,'[REDACTED]');});
 }
 public function test_live_retry_refreshes_stale_shell_access_before_creating_the_account():void
 {
  config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>true]);
  Http::fake([
   'https://whm.example.test:2087/json-api/listpkgs*'=>Http::sequence()
    ->push(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['pkg'=>[['name'=>'Standard','HASSHELL'=>0]]]])
    ->push(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['pkg'=>[['name'=>'Standard','HASSHELL'=>1]]]]),
   'https://whm.example.test:2087/json-api/listaccts*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[]]]),
   'https://whm.example.test:2087/json-api/createacct*'=>Http::response(['metadata'=>['result'=>0,'reason'=>'Stopped after prerequisite test']]),
  ]);
  $admin=$this->user('admin');$customer=$this->customer();
  $server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret'],'metadata'=>['ssh_host_fingerprint'=>str_repeat('a',64)]]);
  $package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'Standard','name'=>'Standard','shell_access'=>false]);
  $this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'refresh-shell.test'))->assertCreated();
  $run=\App\Models\WebsiteProvisioningRun::firstOrFail();
  $this->assertSame('validate_prerequisites',$run->fresh()->failed_step);
  $this->assertFalse($package->fresh()->shell_access);
  $this->actingAs($admin)->postJson("/api/website-provisioning/{$run->id}/retry")->assertAccepted();
  $this->assertTrue($package->fresh()->shell_access);
  $this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'validate_prerequisites','status'=>'complete','attempts'=>2]);
  $this->assertDatabaseHas('website_provisioning_runs',['id'=>$run->id,'failed_step'=>'create_cpanel_account']);
  Http::assertSent(fn($request)=>str_contains($request->url(),'/createacct')&&$request['plan']==='Standard'&&!array_key_exists('hasshell',$request->data()));
 }
 public function test_shell_enablement_failure_stops_at_connect_ssh_before_wordpress_commands():void
 {
  config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>true]);
  Http::fake(['https://whm.example.test:2087/json-api/accountsummary*'=>Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>'shellblocked','shell'=>'/usr/local/cpanel/bin/noshell']]]]),'https://whm.example.test:2087/json-api/modifyacct*'=>Http::response(['metadata'=>['result'=>0,'reason'=>'Permission denied']])]);
  $ssh=new class implements \App\Contracts\SshCommandRunner { public array $commands=[];public function run(HostingServer $server,HostingAccount $account,string $password,string $command,int $timeout=60):array{$this->commands[]=$command;return['exit_code'=>0,'stdout'=>'__WEBSTAMP_CONNECTED__','stderr'=>''];} };
  $this->app->instance(\App\Contracts\SshCommandRunner::class,$ssh);
  $admin=$this->user('admin');$customer=$this->customer();$server=$this->whmServer();
  $package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'Standard','name'=>'Standard','shell_access'=>true]);
  $website=\App\Models\Website::create(['customer_id'=>$customer->id,'hosting_server_id'=>$server->id,'name'=>'Shell blocked','domain'=>'shell-blocked.test','login_url'=>'https://shell-blocked.test/wp-admin/','environment'=>'production','wordpress_enabled'=>true,'management_enabled'=>true,'hosting_enabled'=>true,'provisioning_status'=>'pending','status'=>'unknown','portal_visibility'=>\App\Models\Website::defaultPortalVisibility()]);
  $account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'shellblocked','username'=>'shellblocked','primary_domain'=>'shell-blocked.test','status'=>'active']);
  $run=\App\Models\WebsiteProvisioningRun::create(['public_id'=>(string)\Illuminate\Support\Str::uuid(),'website_id'=>$website->id,'hosting_server_id'=>$server->id,'hosting_package_id'=>$package->id,'hosting_account_id'=>$account->id,'initiated_by_user_id'=>$admin->id,'idempotency_key'=>(string)\Illuminate\Support\Str::uuid(),'domain'=>'shell-blocked.test','mode'=>'live','website_type'=>'wordpress','secrets_encrypted'=>['cpanel_password'=>'not-logged']]);
  foreach(['connect_ssh','download_wordpress','create_wp_config']as$step)$run->steps()->create(['step'=>$step]);
  app(\App\Services\Hosting\WebsiteProvisioner::class)->process($run);
  $this->assertDatabaseHas('website_provisioning_runs',['id'=>$run->id,'state'=>'failed','failed_step'=>'connect_ssh']);
  $this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'connect_ssh','status'=>'failed']);
  $this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'download_wordpress','status'=>'pending']);
  $this->assertSame([],$ssh->commands);
 }
 public function test_controlled_shell_recovery_resets_only_ssh_dependent_steps_and_preserves_database_state():void
 {
  Queue::fake();$admin=$this->user('admin');$customer=$this->customer();$server=$this->whmServer();
  $package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'Standard','name'=>'Standard','shell_access'=>true]);
  $website=\App\Models\Website::create(['customer_id'=>$customer->id,'hosting_server_id'=>$server->id,'name'=>'Recover','domain'=>'recover.test','login_url'=>'https://recover.test/wp-admin/','environment'=>'production','wordpress_enabled'=>true,'management_enabled'=>true,'hosting_enabled'=>true,'provisioning_status'=>'failed','status'=>'unknown','portal_visibility'=>\App\Models\Website::defaultPortalVisibility()]);
  $account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'recover1','username'=>'recover1','primary_domain'=>'recover.test','status'=>'active']);
  $secrets=['cpanel_password'=>'preserve-cpanel','database_password'=>'preserve-database','database_name'=>'recover_wp','database_user'=>'recover_wpuser'];
  $run=\App\Models\WebsiteProvisioningRun::create(['public_id'=>(string)\Illuminate\Support\Str::uuid(),'website_id'=>$website->id,'hosting_server_id'=>$server->id,'hosting_package_id'=>$package->id,'hosting_account_id'=>$account->id,'initiated_by_user_id'=>$admin->id,'idempotency_key'=>(string)\Illuminate\Support\Str::uuid(),'domain'=>'recover.test','mode'=>'live','website_type'=>'wordpress','state'=>'failed','failed_step'=>'verify_wordpress','safe_error'=>'Old false positive','secrets_encrypted'=>$secrets]);
  $sshSteps=['connect_ssh','download_wordpress','create_wp_config','install_wordpress','configure_wordpress','verify_wordpress'];
  $preservedSteps=['validate_prerequisites','create_cpanel_account','wait_for_cpanel','create_database','create_database_user','grant_database_privileges'];
  foreach($preservedSteps as$step)$run->steps()->create(['step'=>$step,'status'=>'complete','attempts'=>1,'completed_at'=>now()]);
  foreach($sshSteps as$step)$run->steps()->create(['step'=>$step,'status'=>$step==='verify_wordpress'?'failed':'complete','attempts'=>1,'completed_at'=>now()]);
  $this->actingAs($admin)->postJson("/api/website-provisioning/{$run->id}/retry",['recover_ssh_steps'=>true])->assertAccepted();
  $this->assertSame(6,$run->steps()->whereIn('step',$sshSteps)->where('status','pending')->count());
  $this->assertSame(6,$run->steps()->whereIn('step',$preservedSteps)->where('status','complete')->count());
  $this->assertSame($secrets,$run->fresh()->secrets_encrypted);
  Queue::assertPushed(\App\Jobs\ProcessWebsiteProvisioning::class,fn($job)=>$job->runId===$run->id);
 }
 public function test_complete_live_wordpress_pipeline_reaches_every_stage_without_false_success():void
 {
  config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>true]);
  $createdUsername=null;
  Http::fake(function($request)use(&$createdUsername){$url=$request->url();if(str_contains($url,'/listpkgs'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['pkg'=>[['name'=>'Standard','HASSHELL'=>1]]]]);if(str_contains($url,'/listaccts'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>$createdUsername?[['user'=>$createdUsername,'domain'=>'pipeline.test','ip'=>'1.2.3.4','plan'=>'Standard','suspended'=>0]]:[]]]);if(str_contains($url,'/accountsummary'))return Http::response(['metadata'=>['result'=>1,'reason'=>'OK'],'data'=>['acct'=>[['user'=>$createdUsername,'shell'=>'/usr/local/cpanel/bin/jailshell']]]]);if(str_contains($url,'/createacct')){$createdUsername=$request['username'];return Http::response(['metadata'=>['result'=>1,'reason'=>'Account created'],'data'=>['ip'=>'1.2.3.4']]);}if(str_contains($url,'/json-api/uapi_cpanel')){$data=$request['cpanel.function']==='get_restrictions'?['prefix'=>'pipeline_','max_database_name_length'=>64,'max_username_length'=>32]:null;return Http::response(['metadata'=>['reason'=>'OK','command'=>'uapi_cpanel','result'=>1,'version'=>1],'data'=>['uapi'=>['status'=>1,'messages'=>null,'errors'=>null,'metadata'=>[],'warnings'=>null,'data'=>$data]]]);}if(str_starts_with($url,'http://pipeline.test/'))return Http::response('',301,['Location'=>'https://pipeline.test/']);if(str_starts_with($url,'https://pipeline.test/'))return Http::response('',200);return Http::response([],404);});
  $ssh=new class implements \App\Contracts\SshCommandRunner { public array $commands=[];private int $configChecks=0;public function run(HostingServer $server,HostingAccount $account,string $password,string $command,int $timeout=60):array{$this->commands[]=$command;if(str_contains($command,'__WEBSTAMP_CONNECTED__'))return['exit_code'=>0,'stdout'=>'__WEBSTAMP_CONNECTED__','stderr'=>''];if(str_contains($command,'__WEBSTAMP_TOOLS_READY__'))return['exit_code'=>0,'stdout'=>'__WEBSTAMP_TOOLS_READY__','stderr'=>''];if(str_contains($command,"'option' 'get' 'siteurl'")||str_contains($command,"'option' 'get' 'home'"))return['exit_code'=>0,'stdout'=>'https://pipeline.test','stderr'=>''];if(str_contains($command,"'db' 'tables'"))return['exit_code'=>0,'stdout'=>'wp_posts','stderr'=>''];if(str_contains($command,'test -f public_html/wp-config.php')){$this->configChecks++;return['exit_code'=>$this->configChecks===1?1:0,'stdout'=>'','stderr'=>''];}if(str_contains($command,'test -f public_html/wp-load.php')||str_contains($command,"'core' 'is-installed'"))return['exit_code'=>1,'stdout'=>'','stderr'=>''];return['exit_code'=>0,'stdout'=>'','stderr'=>''];} };
  $this->app->instance(\App\Contracts\SshCommandRunner::class,$ssh);
  $this->app->instance(\App\Contracts\DnsResolver::class,new class implements \App\Contracts\DnsResolver { public function aRecords(string $host):array{return['1.2.3.4'];}public function cnameRecords(string $host):array{return[];}public function nameservers(string $host):array{return[];} });
  $this->app->instance(\App\Contracts\SslInspector::class,new class implements \App\Contracts\SslInspector { public function inspect(string $host):array{return['valid'=>true,'hostname_match'=>true,'issuer'=>'Test CA','expires_at'=>now()->addDays(30)->toIso8601String(),'error'=>null];} });
  $admin=$this->user('admin');$customer=$this->customer();
  $server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'secret'],'metadata'=>['ssh_host_fingerprint'=>str_repeat('a',64)]]);
  $package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'Standard','name'=>'Standard','shell_access'=>true]);
  $response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'pipeline.test'))->assertCreated();
  $run=\App\Models\WebsiteProvisioningRun::firstOrFail();
  $this->assertSame('complete',$run->fresh()->state,json_encode(['failed_step'=>$run->fresh()->failed_step,'safe_error'=>$run->fresh()->safe_error]));
  $this->assertDatabaseMissing('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'status'=>'failed']);
  $this->assertSame(1,$run->steps()->where('status','manual_action')->where('step','install_agent')->count());
  $this->assertSame($run->steps()->count()-1,$run->steps()->where('status','complete')->count());
  $this->assertNotNull($createdUsername);
  $this->assertDatabaseHas('hosting_accounts',['username'=>$createdUsername,'primary_domain'=>'pipeline.test','assigned_ip'=>'1.2.3.4']);
  $this->assertDatabaseHas('websites',['domain'=>'pipeline.test','provisioning_status'=>'complete','status'=>'healthy']);
  $this->assertStringNotContainsString('secret',strtolower($response->getContent()));
  $this->assertNotEmpty($ssh->commands);
  $this->assertFalse(collect($ssh->commands)->contains(fn($command)=>str_contains($command,'uapi')));
  Http::assertSent(fn($request)=>str_contains($request->url(),'/json-api/uapi_cpanel')&&$request['cpanel.function']==='create_database'&&$request['name']==='pipeline_wp');
  Http::assertSent(fn($request)=>str_contains($request->url(),'/json-api/uapi_cpanel')&&$request['cpanel.function']==='create_user'&&$request['name']==='pipeline_wpuser');
  Http::assertSent(fn($request)=>str_contains($request->url(),'/json-api/uapi_cpanel')&&$request['cpanel.function']==='set_privileges_on_database'&&$request['database']==='pipeline_wp'&&$request['user']==='pipeline_wpuser'&&$request['privileges']==='ALL');
 }
 public function test_repair_migration_removes_only_unverified_mock_connections():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'fake-preview.test'))->assertCreated();$website=\App\Models\Website::findOrFail($response->json('data.website.id'));$website->update(['agent_last_seen_at'=>now(),'monitoring_enabled'=>true]);$accountId=$website->hosting_account_id;$migration=require database_path('migrations/2026_08_22_000200_repair_mock_provisioning_false_positives.php');$migration->up();$website->refresh();$this->assertNull($website->hosting_account_id);$this->assertNull($website->agent_last_seen_at);$this->assertSame('failed',$website->provisioning_status);$this->assertDatabaseMissing('hosting_accounts',['id'=>$accountId]);}
 public function test_repair_migration_preserves_a_later_real_whm_mapping():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'real-after-preview.test'))->assertCreated();$website=\App\Models\Website::findOrFail($response->json('data.website.id'));$account=$website->hostingAccount;$account->update(['metadata'=>['owner'=>'reseller'],'last_synced_at'=>now()]);$before=$website->provisioning_status;$migration=require database_path('migrations/2026_08_22_000200_repair_mock_provisioning_false_positives.php');$migration->up();$this->assertSame($account->id,$website->fresh()->hosting_account_id);$this->assertSame($before,$website->fresh()->provisioning_status);}
 public function test_generated_wordpress_credentials_are_encrypted_hidden_and_revealed_once():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'credentials.test'))->assertCreated();$websiteId=$response->json('data.website.id');$this->assertStringNotContainsString('password',strtolower($response->getContent()));$credential=\App\Models\WebsiteCredential::firstOrFail();$this->assertNotSame($credential->secret_encrypted,$credential->getRawOriginal('secret_encrypted'));$this->actingAs($admin)->postJson("/api/websites/{$websiteId}/reveal-credential")->assertOk()->assertJsonPath('data.username','webstamp_admin');$this->actingAs($admin)->postJson("/api/websites/{$websiteId}/reveal-credential")->assertStatus(410);}
 public function test_live_mode_fails_closed_and_customers_cannot_provision():void{config(['hosting.provisioning_mode'=>'live','hosting.allow_live_provisioning'=>false]);$admin=$this->user('admin');$customerUser=$this->user('customer');$customer=$this->customer($customerUser);[$server,$package]=$this->hosting();$payload=$this->payload($customer,$server,$package);$this->actingAs($admin)->postJson('/api/website-provisioning',$payload)->assertUnprocessable();$this->actingAs($customerUser)->postJson('/api/website-provisioning',$payload)->assertForbidden();$this->assertDatabaseCount('websites',0);}
 public function test_admin_without_provisioning_permission_cannot_provision():void{$admin=$this->user('admin');$admin->roles()->firstOrFail()->permissions()->where('slug','hosting_provision')->detach();$customer=$this->customer();[$server,$package]=$this->hosting();$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package))->assertForbidden();$this->assertDatabaseCount('websites',0);}
 public function test_failed_provisioning_preserves_local_record_and_never_deletes_hosting():void{$admin=$this->user('admin');$customer=$this->customer();$server=HostingServer::create(['name'=>'Mock failure','api_type'=>'mock','metadata'=>['mock_fail_steps'=>['create_cpanel_account']]]);$package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'standard','name'=>'Standard']);$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'failure.test'))->assertCreated()->assertJsonPath('data.state','failed');$this->assertDatabaseHas('websites',['domain'=>'failure.test','provisioning_status'=>'failed']);$this->assertNull(\App\Models\Website::where('domain','failure.test')->value('cpanel_username'));$run=\App\Models\WebsiteProvisioningRun::firstOrFail();$this->assertArrayHasKey('cpanel_username_proposal',$run->secrets_encrypted);$this->assertDatabaseHas('website_provisioning_runs',['domain'=>'failure.test','state'=>'failed','failed_step'=>'create_cpanel_account']);$this->assertDatabaseCount('hosting_accounts',0);}
 public function test_retry_resumes_at_the_failed_step_without_repeating_completed_steps():void{$admin=$this->user('admin');$customer=$this->customer();$server=HostingServer::create(['name'=>'Mock failure','api_type'=>'mock','metadata'=>['mock_fail_steps'=>['wait_for_cpanel']]]);$package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'standard','name'=>'Standard']);$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'retry.test'))->assertCreated()->assertJsonPath('data.state','failed');$run=\App\Models\WebsiteProvisioningRun::firstOrFail();$this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'create_cpanel_account','status'=>'complete','attempts'=>1]);$this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'wait_for_cpanel','status'=>'failed','attempts'=>1]);$server->update(['metadata'=>[]]);$this->actingAs($admin)->postJson("/api/website-provisioning/{$run->id}/retry")->assertAccepted();$this->assertDatabaseHas('website_provisioning_runs',['id'=>$run->id,'state'=>'complete']);$this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'create_cpanel_account','status'=>'complete','attempts'=>1]);$this->assertDatabaseHas('website_provisioning_steps',['website_provisioning_run_id'=>$run->id,'step'=>'wait_for_cpanel','status'=>'complete','attempts'=>2]);$this->assertDatabaseCount('hosting_accounts',1);}
 public function test_unsafe_domain_and_wordpress_admin_inputs_are_rejected_before_any_account_is_created():void{$admin=$this->user('admin');$customer=$this->customer();[$server,$package]=$this->hosting();$payload=$this->payload($customer,$server,$package,'safe.test');$payload['domain']='safe.test; touch bad';$payload['options']=['admin_username'=>'admin'];$this->actingAs($admin)->postJson('/api/website-provisioning',$payload)->assertUnprocessable()->assertJsonValidationErrors(['domain','options.admin_username']);$this->assertDatabaseCount('websites',0);$this->assertDatabaseCount('hosting_accounts',0);}
 public function test_temporary_cpanel_and_database_secrets_are_encrypted_and_never_returned():void{$admin=$this->user('admin');$customer=$this->customer();$server=HostingServer::create(['name'=>'Mock pause','api_type'=>'mock','metadata'=>['mock_fail_steps'=>['wait_for_cpanel']]]);$package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'standard','name'=>'Standard']);$response=$this->actingAs($admin)->postJson('/api/website-provisioning',$this->payload($customer,$server,$package,'encrypted.test'))->assertCreated();$run=\App\Models\WebsiteProvisioningRun::firstOrFail();$this->assertArrayHasKey('cpanel_password',$run->secrets_encrypted);$this->assertArrayHasKey('database_password',$run->secrets_encrypted);$raw=(string)$run->getRawOriginal('secrets_encrypted');$this->assertStringNotContainsString($run->secrets_encrypted['cpanel_password'],$raw);$this->assertStringNotContainsString($run->secrets_encrypted['cpanel_password'],$response->getContent());$this->assertStringNotContainsString($run->secrets_encrypted['database_password'],$response->getContent());}
 private function whmServer(string $token='secret'):HostingServer{return HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>$token]]);}
 private function singleStepLiveRun(string $domain):array{$admin=$this->user('admin');$customer=$this->customer();$server=$this->whmServer();$package=HostingPackage::create(['hosting_server_id'=>$server->id,'external_id'=>'Standard','name'=>'Standard','shell_access'=>true]);$website=\App\Models\Website::create(['customer_id'=>$customer->id,'hosting_server_id'=>$server->id,'name'=>'Provisioned site','domain'=>$domain,'login_url'=>'https://'.$domain.'/wp-admin/','environment'=>'production','wordpress_enabled'=>true,'management_enabled'=>true,'hosting_enabled'=>true,'provisioning_status'=>'pending','status'=>'unknown','portal_visibility'=>\App\Models\Website::defaultPortalVisibility()]);$run=\App\Models\WebsiteProvisioningRun::create(['public_id'=>(string)\Illuminate\Support\Str::uuid(),'website_id'=>$website->id,'hosting_server_id'=>$server->id,'hosting_package_id'=>$package->id,'initiated_by_user_id'=>$admin->id,'idempotency_key'=>(string)\Illuminate\Support\Str::uuid(),'domain'=>$domain,'mode'=>'live','website_type'=>'wordpress']);$run->steps()->create(['step'=>'create_cpanel_account']);return[$run,$website];}
 private function whmUapiAccount():array{$server=HostingServer::create(['name'=>'Krystal','api_type'=>'whm','hostname'=>'whm.example.test','credentials'=>['username'=>'reseller','token'=>'whm-secret']]);$account=HostingAccount::create(['hosting_server_id'=>$server->id,'external_id'=>'newclient','username'=>'newclient','primary_domain'=>'newclient.test','status'=>'active']);return[$server,$account];}
 private function hosting():array{$s=HostingServer::create(['name'=>'Mock','api_type'=>'mock']);$p=HostingPackage::create(['hosting_server_id'=>$s->id,'external_id'=>'standard','name'=>'Standard']);return[$s,$p];} private function payload($c,$s,$p,string $domain='new-site.test'):array{return['customer_id'=>$c->id,'name'=>'New site','domain'=>$domain,'environment'=>'development','hosting_server_id'=>$s->id,'hosting_package_id'=>$p->id,'website_type'=>'wordpress','options'=>[],'idempotency_key'=>'same-request'];} private function user(string $role):User{$u=User::factory()->create();$u->roles()->attach(Role::where('slug',$role)->firstOrFail());return$u;} private function customer(?User $u=null):Customer{return Customer::create(['name'=>'Client','email'=>fake()->unique()->safeEmail(),'billing_address'=>'1 Test Road','user_id'=>$u?->id]);}
}
