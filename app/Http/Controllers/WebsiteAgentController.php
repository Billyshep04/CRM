<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use Illuminate\Http\Request;
use App\Services\Websites\WebsiteHealthEvaluator;
use App\Services\Websites\WebsiteStatusSnapshot;

class WebsiteAgentController extends Controller
{
    public function status(Request $request, Website $website, WebsiteHealthEvaluator $health, WebsiteStatusSnapshot $snapshots)
    {
        $provided = $request->bearerToken() ?: $request->header('X-WebStamp-Token');
        if (!$provided || !$website->agent_token_hash || !hash_equals($website->agent_token_hash, hash('sha256', $provided))) abort(401);
        $data = $request->validate([
            'wordpress_version' => ['nullable', 'string', 'max:50'], 'php_version' => ['nullable', 'string', 'max:50'], 'plugin_count' => ['nullable', 'integer', 'min:0'], 'plugin_updates' => ['nullable', 'integer', 'min:0'], 'theme_updates' => ['nullable', 'integer', 'min:0'],
            'database_size_bytes' => ['nullable', 'integer', 'min:0'], 'site_health_status' => ['nullable', 'string', 'max:50'], 'last_successful_backup_at' => ['nullable', 'date'], 'backup_status' => ['nullable', 'string', 'max:50'], 'performance_score' => ['nullable', 'integer', 'between:0,100'], 'metrics' => ['nullable', 'array'],
            'active_theme' => ['nullable','string','max:255'], 'active_theme_version' => ['nullable','string','max:50'], 'active_plugin_count' => ['nullable','integer','min:0'], 'inactive_plugin_count' => ['nullable','integer','min:0'], 'core_updates' => ['nullable','integer','min:0'], 'wp_cron_status' => ['nullable','string','max:50'], 'maintenance_mode' => ['nullable','boolean'],
        ]);
        $extra=collect($data)->only(['active_theme','active_theme_version','active_plugin_count','inactive_plugin_count','core_updates','wp_cron_status','maintenance_mode'])->all();
        if (array_key_exists('performance_score', $data)) $extra['agent_reported_performance_score'] = $data['performance_score'];
        $columns=collect($data)->except([...array_keys($extra), 'performance_score'])->all(); $columns['metrics']=[...($columns['metrics']??[]),...$extra];
        $evaluation=$health->evaluate(['online'=>true,'updates_available'=>($data['plugin_updates']??0)+($data['theme_updates']??0)+($data['core_updates']??0),'last_successful_backup_at'=>isset($data['last_successful_backup_at'])?\Carbon\Carbon::parse($data['last_successful_backup_at']):null]);
        $check = WebsiteHealthCheck::create([
            ...$columns, 'website_id' => $website->id, 'checked_at' => now(),
            'wordpress_checked_at' => now(),
            'backup_checked_at' => (array_key_exists('backup_status', $data) || array_key_exists('last_successful_backup_at', $data)) ? now() : null,
            'performance_checked_at' => null,
            'check_type' => 'agent', 'uptime_status' => 'unknown', 'overall_status' => 'unknown', 'warnings'=>$evaluation['issues'],
        ]);
        $website->update(['agent_last_seen_at' => now(), 'agent_last_failed_at' => null, 'monitoring_enabled'=>true]);
        $snapshot = $snapshots->for($website->fresh());
        $check->update(['overall_status' => $snapshot['overall_status']]);
        $website->update(['status' => $snapshot['overall_status']]);
        return response()->json(['message' => 'Status received.'], 202);
    }
}
