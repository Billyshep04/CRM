<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use Illuminate\Http\Request;
use App\Services\Websites\WebsiteHealthEvaluator;

class WebsiteAgentController extends Controller
{
    public function status(Request $request, Website $website, WebsiteHealthEvaluator $health)
    {
        $provided = $request->bearerToken() ?: $request->header('X-WebStamp-Token');
        if (!$provided || !$website->agent_token_hash || !hash_equals($website->agent_token_hash, hash('sha256', $provided))) abort(401);
        $data = $request->validate([
            'wordpress_version' => ['nullable', 'string', 'max:50'], 'php_version' => ['nullable', 'string', 'max:50'], 'plugin_count' => ['nullable', 'integer', 'min:0'], 'plugin_updates' => ['nullable', 'integer', 'min:0'], 'theme_updates' => ['nullable', 'integer', 'min:0'],
            'database_size_bytes' => ['nullable', 'integer', 'min:0'], 'site_health_status' => ['nullable', 'string', 'max:50'], 'last_successful_backup_at' => ['nullable', 'date'], 'backup_status' => ['nullable', 'string', 'max:50'], 'performance_score' => ['nullable', 'integer', 'between:0,100'], 'metrics' => ['nullable', 'array'],
            'active_theme' => ['nullable','string','max:255'], 'active_theme_version' => ['nullable','string','max:50'], 'active_plugin_count' => ['nullable','integer','min:0'], 'inactive_plugin_count' => ['nullable','integer','min:0'], 'core_updates' => ['nullable','integer','min:0'], 'wp_cron_status' => ['nullable','string','max:50'], 'maintenance_mode' => ['nullable','boolean'],
        ]);
        $extra=collect($data)->only(['active_theme','active_theme_version','active_plugin_count','inactive_plugin_count','core_updates','wp_cron_status','maintenance_mode'])->all();
        $columns=collect($data)->except(array_keys($extra))->all(); $columns['metrics']=[...($columns['metrics']??[]),...$extra];
        $evaluation=$health->evaluate(['online'=>true,'updates_available'=>($data['plugin_updates']??0)+($data['theme_updates']??0)+($data['core_updates']??0),'last_successful_backup_at'=>isset($data['last_successful_backup_at'])?\Carbon\Carbon::parse($data['last_successful_backup_at']):null]);
        $check = WebsiteHealthCheck::create([...$columns, 'website_id' => $website->id, 'checked_at' => now(), 'check_type' => 'agent', 'uptime_status' => 'online', 'overall_status' => $evaluation['status'], 'warnings'=>$evaluation['issues']]);
        $website->update(['agent_last_seen_at' => now(), 'last_checked_at' => now(), 'monitoring_enabled'=>true, 'status' => $check->overall_status, 'consecutive_failures' => 0]);
        return response()->json(['message' => 'Status received.'], 202);
    }
}
