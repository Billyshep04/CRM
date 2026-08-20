<?php
namespace App\Services\Websites;

use Carbon\CarbonInterface;

class WebsiteHealthEvaluator
{
    public function evaluate(array $data): array
    {
        $issues=[]; $critical=false;
        if (($data['online']??true) === false) { $issues[]='Website is offline.'; $critical=true; }
        if (($data['ssl_valid']??true) === false) { $issues[]='SSL certificate is invalid.'; $critical=true; }
        $disk=$this->percent($data['disk_used_bytes']??null,$data['disk_limit_bytes']??null);
        if ($disk !== null && $disk >= config('hosting.health.disk_critical_percent',92)) { $issues[]="Disk usage is {$disk}%."; $critical=true; }
        elseif ($disk !== null && $disk >= config('hosting.health.disk_warning_percent',80)) $issues[]="Disk usage is {$disk}%.";
        if (($data['updates_available']??0)>0) $issues[]=$data['updates_available'].' WordPress updates available.';
        $backup=$data['last_successful_backup_at']??null;
        if ($backup instanceof CarbonInterface && $backup->lt(now()->subHours(config('hosting.health.backup_warning_hours',48)))) $issues[]='Latest successful backup is overdue.';
        return ['status'=>$critical?'critical':($issues?'attention':'healthy'),'issues'=>$issues,'disk_percent'=>$disk];
    }
    private function percent(?int $used,?int $limit):?int { return $used!==null&&$limit?min(100,(int)round($used/$limit*100)):null; }
}
