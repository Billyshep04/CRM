<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteDeletionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void { parent::setUp(); $this->seed(RolePermissionSeeder::class); config(['hosting.termination_mode'=>'mock','hosting.allow_live_termination'=>false]); }

    public function test_customer_and_staff_cannot_access_deletion_endpoints():void
    {
        [$website]=$this->records();
        foreach(['customer','staff'] as $role){$user=$this->user($role);$this->actingAs($user)->getJson("/api/websites/{$website->id}/deletion-preview")->assertForbidden();$this->actingAs($user)->postJson("/api/websites/{$website->id}/delete",[])->assertForbidden();}
    }

    public function test_crm_only_deletion_leaves_hosting_and_keeps_audit():void
    {
        [$website,$account]=$this->records(); $admin=$this->user('admin');
        $this->actingAs($admin)->postJson("/api/websites/{$website->id}/delete",['deletion_type'=>'crm_only','confirmation'=>'example.test','idempotency_key'=>(string)Str::uuid()])->assertOk();
        $this->assertSoftDeleted('websites',['id'=>$website->id]); $this->assertDatabaseHas('hosting_accounts',['id'=>$account->id]); $this->assertDatabaseHas('website_deletion_audits',['website_id'=>$website->id,'deletion_type'=>'crm_only','state'=>'complete']);
    }

    public function test_mock_full_deletion_terminates_exclusive_account_and_is_audited():void
    {
        [$website,$account]=$this->records(); $admin=$this->user('admin');
        $this->actingAs($admin)->getJson("/api/websites/{$website->id}/deletion-preview")->assertOk()->assertJsonPath('data.hosting_termination_allowed',true);
        $this->actingAs($admin)->postJson("/api/websites/{$website->id}/delete",['deletion_type'=>'hosting_and_crm','confirmation'=>'example.test','backup_confirmed'=>true,'idempotency_key'=>(string)Str::uuid()])->assertOk();
        $this->assertSoftDeleted('websites',['id'=>$website->id]); $this->assertDatabaseMissing('hosting_accounts',['id'=>$account->id]); $this->assertDatabaseHas('website_deletion_audits',['website_id'=>$website->id,'deletion_type'=>'hosting_and_crm','state'=>'complete']);
    }

    public function test_shared_account_and_wrong_confirmation_block_full_deletion():void
    {
        [$website,$account,$customer]=$this->records(); Website::create(['customer_id'=>$customer->id,'hosting_server_id'=>$account->hosting_server_id,'hosting_account_id'=>$account->id,'name'=>'Other','domain'=>'other.test','login_url'=>'https://other.test','hosting_enabled'=>true]); $admin=$this->user('admin');
        $this->actingAs($admin)->getJson("/api/websites/{$website->id}/deletion-preview")->assertJsonPath('data.hosting_termination_allowed',false);
        $this->actingAs($admin)->postJson("/api/websites/{$website->id}/delete",['deletion_type'=>'hosting_and_crm','confirmation'=>'example.test','backup_confirmed'=>true,'idempotency_key'=>(string)Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseHas('websites',['id'=>$website->id]); $this->assertDatabaseHas('hosting_accounts',['id'=>$account->id]);
    }

    public function test_failed_hosting_termination_preserves_every_mapping_and_records_failure():void
    {
        [$website,$account]=$this->records(); $account->server->update(['metadata'=>['mock_fail_steps'=>['terminate_account']]]); $admin=$this->user('admin');
        $this->actingAs($admin)->postJson("/api/websites/{$website->id}/delete",['deletion_type'=>'hosting_and_crm','confirmation'=>'example.test','backup_confirmed'=>true,'idempotency_key'=>(string)Str::uuid()])->assertUnprocessable()->assertJsonPath('message','Mock terminate_account failure.');
        $this->assertDatabaseHas('websites',['id'=>$website->id,'hosting_account_id'=>$account->id,'deletion_status'=>'failed']); $this->assertDatabaseHas('hosting_accounts',['id'=>$account->id]); $this->assertDatabaseHas('website_deletion_audits',['website_id'=>$website->id,'state'=>'failed']);
    }

    private function records():array
    {
        $customer=Customer::create(['name'=>'Client','email'=>fake()->unique()->safeEmail(),'billing_address'=>'1 Road']); $server=HostingServer::create(['name'=>'Mock Krystal','provider'=>'krystal','api_type'=>'mock']);
        $account=HostingAccount::create(['hosting_server_id'=>$server->id,'customer_id'=>$customer->id,'external_id'=>'example','username'=>'example','primary_domain'=>'example.test','domains'=>[['domain'=>'example.test','type'=>'primary']],'status'=>'active']);
        $website=Website::create(['customer_id'=>$customer->id,'hosting_server_id'=>$server->id,'hosting_account_id'=>$account->id,'name'=>'Example','domain'=>'example.test','login_url'=>'https://example.test','hosting_enabled'=>true]); return[$website,$account,$customer];
    }
    private function user(string $role):User{$user=User::factory()->create();$user->roles()->attach(Role::where('slug',$role)->firstOrFail());return$user;}
}
