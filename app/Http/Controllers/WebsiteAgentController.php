<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use Illuminate\Http\Request;

class WebsiteAgentController extends Controller
{
    public function status(Request $request, Website $website)
    {
        $provided = $request->bearerToken() ?: $request->header('X-WebStamp-Token');
        if (!$provided || !$website->agent_token_hash || !hash_equals($website->agent_token_hash, hash('sha256', $provided))) abort(401);
        $data = $request->validate([
            'wordpress_version' => ['nullable', 'string', 'max:50'], 'php_version' => ['nullable', 'string', 'max:50'], 'plugin_updates' => ['nullable', 'integer', 'min:0'], 'theme_updates' => ['nullable', 'integer', 'min:0'],
            'database_size_bytes' => ['nullable', 'integer', 'min:0'], 'site_health_status' => ['nullable', 'string', 'max:50'], 'last_successful_backup_at' => ['nullable', 'date'], 'backup_status' => ['nullable', 'string', 'max:50'], 'performance_score' => ['nullable', 'integer', 'between:0,100'], 'metrics' => ['nullable', 'array'],
        ]);
        $check = WebsiteHealthCheck::create([...$data, 'website_id' => $website->id, 'checked_at' => now(), 'check_type' => 'agent', 'uptime_status' => 'online', 'overall_status' => (($data['plugin_updates'] ?? 0) + ($data['theme_updates'] ?? 0)) > 0 ? 'attention' : 'healthy']);
        $website->update(['agent_last_seen_at' => now(), 'last_checked_at' => now(), 'status' => $check->overall_status, 'consecutive_failures' => 0]);
        return response()->json(['message' => 'Status received.'], 202);
    }
}
