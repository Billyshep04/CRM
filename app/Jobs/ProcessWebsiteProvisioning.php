<?php
namespace App\Jobs;
use App\Models\WebsiteProvisioningRun; use App\Services\Hosting\WebsiteProvisioner; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels;
class ProcessWebsiteProvisioning implements ShouldQueue { use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public int $tries=1; public function __construct(public int $runId){} public function handle(WebsiteProvisioner $provisioner):void{if($run=WebsiteProvisioningRun::find($this->runId))$provisioner->process($run);} }
