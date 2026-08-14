<?php
namespace App\Services\Hosting;
use App\Contracts\HostingProviderInterface;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Website;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KrystalWhmProvider implements HostingProviderInterface
{
    private function client(HostingServer $server): PendingRequest { $c=$server->credentials??[]; if(!$server->hostname||empty($c['username'])||empty($c['token'])) throw new RuntimeException('WHM credentials are not configured.'); return Http::withHeaders(['Authorization'=>'whm '.$c['username'].':'.$c['token']])->acceptJson()->timeout(20)->baseUrl('https://'.preg_replace('/:\d+$/','',$server->hostname).':2087/json-api'); }
    private function call(HostingServer $server,string $fn,array $query=[]): array { $r=$this->client($server)->get('/'.$fn,['api.version'=>1,...$query]); if(!$r->successful()||($r->json('metadata.result')===0)) throw new RuntimeException((string)($r->json('metadata.reason')?:'WHM request failed.')); return $r->json()??[]; }
    public function testConnection(HostingServer $server): array { $this->call($server,'listaccts',['want'=>1]); return ['ok'=>true,'message'=>'Krystal WHM connected.']; }
    public function accounts(HostingServer $server): array { return collect($this->call($server,'listaccts')['data']['acct']??[])->map(fn($a)=>['external_id'=>(string)$a['user'],'username'=>(string)$a['user'],'primary_domain'=>$a['domain']??null,'package_name'=>$a['plan']??null,'status'=>($a['suspended']??0)?'suspended':'active','disk_used_bytes'=>$this->bytes($a['diskused']??null),'disk_limit_bytes'=>$this->bytes($a['disklimit']??null),'metadata'=>['owner'=>$a['owner']??null,'email'=>$a['email']??null]])->all(); }
    public function packages(HostingServer $server): array { return collect($this->call($server,'listpkgs')['data']['pkg']??[])->map(fn($p)=>['external_id'=>(string)$p['name'],'name'=>(string)$p['name'],'limits'=>$p])->all(); }
    public function createAccount(HostingServer $server,array $data): array { $r=$this->call($server,'createacct',['username'=>$data['username'],'domain'=>$data['domain'],'password'=>$data['password'],'plan'=>$data['package_name']]); return ['external_id'=>$data['username'],'username'=>$data['username'],'primary_domain'=>$data['domain'],'package_name'=>$data['package_name'],'status'=>'active','metadata'=>['whm_message'=>$r['metadata']['reason']??'Created']]; }
    public function accountMetrics(HostingServer $server,Website $website): array { $a=$website->hostingAccount; return $a?['disk_used_bytes'=>$a->disk_used_bytes,'disk_limit_bytes'=>$a->disk_limit_bytes,'bandwidth_used_bytes'=>$a->bandwidth_used_bytes]:[]; }
    public function cpanelSession(HostingServer $server,HostingAccount $account): ?string { $r=$this->call($server,'create_user_session',['user'=>$account->username,'service'=>'cpaneld']); return $r['data']['url']??null; }
    private function bytes(mixed $v): ?int { if(!is_numeric($v))return null; return (int)round((float)$v*1024*1024); }
}
