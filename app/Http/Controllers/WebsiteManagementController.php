<?php
namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteCredential;
use App\Services\Websites\WebsiteDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class WebsiteManagementController extends Controller
{
    public function deletionPreview(Website $website, WebsiteDeletionService $service){return response()->json(['data'=>$service->preview($website)]);}
    public function delete(Request $request,Website $website,WebsiteDeletionService $service){$d=$request->validate(['deletion_type'=>['required',Rule::in(['crm_only','hosting_and_crm'])],'confirmation'=>['required','string','max:255'],'backup_confirmed'=>['exclude_unless:deletion_type,hosting_and_crm','required','accepted'],'idempotency_key'=>['required','uuid']]);try{$audit=$service->delete($website,$d['deletion_type'],$d['confirmation'],$d['idempotency_key'],$request->user()->id,(bool)($d['backup_confirmed']??false));return response()->json(['data'=>$audit,'message'=>'Website deletion completed.']);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}}
    public function revealCredential(Website $website)
    {
        $credential=WebsiteCredential::where('website_id',$website->id)->whereNull('revoked_at')->firstOrFail();
        if ($credential->revealed_at) return response()->json(['message'=>'This generated password has already been revealed. Reset it in WordPress if required.'],410);
        $secret=$credential->secret_encrypted; $credential->update(['revealed_at'=>now()]);
        return response()->json(['data'=>['username'=>$credential->username,'password'=>$secret]]);
    }
}
