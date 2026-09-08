<?php
namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteCredential;
use App\Services\Websites\WebsiteDeletionService;
use App\Services\Hosting\KrystalWordpressProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class WebsiteManagementController extends Controller
{
    public function deletionPreview(Website $website, WebsiteDeletionService $service){return response()->json(['data'=>$service->preview($website)]);}
    public function delete(Request $request,Website $website,WebsiteDeletionService $service){$d=$request->validate(['deletion_type'=>['required',Rule::in(['crm_only','hosting_and_crm'])],'confirmation'=>['required','string','max:255'],'backup_confirmed'=>['exclude_unless:deletion_type,hosting_and_crm','required','accepted'],'idempotency_key'=>['required','uuid']]);try{$audit=$service->delete($website,$d['deletion_type'],$d['confirmation'],$d['idempotency_key'],$request->user()->id,(bool)($d['backup_confirmed']??false));return response()->json(['data'=>$audit,'message'=>'Website deletion completed.']);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}}
    public function revealCredential(Website $website)
    {
        $result = DB::transaction(function () use ($website) {
            $credential = WebsiteCredential::where('website_id', $website->id)->where('type', 'wordpress_admin')->whereNull('revoked_at')->latest('id')->lockForUpdate()->first();
            if (! $credential) return null;
            if ($credential->revealed_at) return false;
            $secret = $credential->secret_encrypted;
            $credential->update(['revealed_at' => now()]);
            return ['username' => $credential->username, 'password' => $secret];
        });
        if ($result === false) return response()->json(['message'=>'This generated password has already been revealed. Reset it in WordPress if required.'],410);
        if ($result === null) return response()->json(['message'=>'No unrevealed generated WordPress login is available. Use Reset WordPress login if automation access is available.'],404);
        return response()->json(['data' => $result]);
    }

    public function resetWordpressLogin(Request $request, Website $website, KrystalWordpressProvisioner $wordpress)
    {
        $website->loadMissing(['hostingServer', 'hostingAccount']);
        if (! $website->wordpress_enabled || ! $website->hostingServer || ! $website->hostingAccount || ! $website->hasVerifiedHostingConnection()) {
            return response()->json(['message' => 'This website does not have a verified WordPress hosting account.'], 422);
        }
        $automationPassword = (string) $website->hostingAccount->automation_password_encrypted;
        if ($automationPassword === '') {
            return response()->json(['message' => 'Automation access is unavailable. Reset this WordPress login manually in cPanel or WordPress.'], 422);
        }
        $existing = WebsiteCredential::where('website_id', $website->id)->where('type', 'wordpress_admin')->latest('id')->first();
        $run = $website->provisioningRuns()->where('website_type', 'wordpress')->latest('id')->first();
        $username = (string) ($existing?->username ?: data_get($run?->options, 'admin_username', config('hosting.wordpress_admin_username', 'webstamp_admin')));
        $newPassword = Str::password(40, true, true, true, false);
        $wordpress->resetAdminPassword($website->hostingServer, $website->hostingAccount, $automationPassword, $username, $newPassword);
        WebsiteCredential::updateOrCreate(
            ['website_id' => $website->id, 'type' => 'wordpress_admin'],
            ['username' => $username, 'secret_encrypted' => $newPassword, 'created_by_user_id' => $request->user()->id, 'revealed_at' => null, 'revoked_at' => null]
        );

        return response()->json(['data' => ['reset' => true, 'reveal_available' => true]]);
    }
}
