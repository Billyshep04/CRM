<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_provisioning_runs') || ! Schema::hasTable('websites')) return;

        if (! Schema::hasColumn('websites', 'agent_last_failed_at')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->timestamp('agent_last_failed_at')->nullable()->after('agent_last_seen_at');
            });
        }

        $runs = DB::table('website_provisioning_runs')->where('mode', 'mock')->get();
        foreach ($runs->groupBy('website_id') as $websiteId => $websiteRuns) {
            $website = DB::table('websites')->where('id', $websiteId)->first();
            if (! $website) continue;

            $account = $website->hosting_account_id && Schema::hasTable('hosting_accounts')
                ? DB::table('hosting_accounts')->where('id', $website->hosting_account_id)->first()
                : null;
            $metadata = $account && is_string($account->metadata ?? null) ? json_decode($account->metadata, true) : [];
            $fakeAccount = $account && (bool) data_get($metadata, 'mock', false);
            $hasVerifiedRealAccount = $account && ! $fakeAccount && $account->last_synced_at !== null;
            if ($hasVerifiedRealAccount) continue;
            $hasRealAgentCheck = Schema::hasTable('website_health_checks') && DB::table('website_health_checks')
                ->where('website_id', $websiteId)
                ->whereNotNull('wordpress_checked_at')
                ->where('check_type', '!=', 'provisioning_mock')
                ->exists();
            $hasRealHealthCheck = Schema::hasTable('website_health_checks') && DB::table('website_health_checks')
                ->where('website_id', $websiteId)
                ->where('check_type', '!=', 'provisioning_mock')
                ->exists();

            $updates = ['provisioning_status' => 'failed', 'lifecycle_state' => 'draft', 'updated_at' => now()];
            if ($fakeAccount) {
                $updates['hosting_account_id'] = null;
                $updates['cpanel_username'] = null;
            }
            if (! $hasRealAgentCheck) {
                $updates['agent_last_seen_at'] = null;
                $updates['agent_last_failed_at'] = null;
                $updates['monitoring_enabled'] = false;
            }
            if (! $hasRealHealthCheck) {
                $updates['status'] = 'unknown';
                $updates['last_checked_at'] = null;
            }
            DB::table('websites')->where('id', $websiteId)->update($updates);

            DB::table('website_provisioning_runs')->whereIn('id', $websiteRuns->pluck('id'))->update([
                'hosting_account_id' => null,
                'state' => 'failed',
                'failed_step' => 'validate_prerequisites',
                'safe_error' => 'This was a preview run and no real WHM account was verified. Enable live provisioning, then use Check again to perform the real setup.',
                'secrets_encrypted' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('website_health_checks')) {
                DB::table('website_health_checks')->where('website_id', $websiteId)->where('check_type', 'provisioning_mock')->delete();
            }
            if ($fakeAccount && ! DB::table('websites')->where('hosting_account_id', $account->id)->exists()) {
                DB::table('hosting_accounts')->where('id', $account->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // False-positive connection state is intentionally not restored.
        if (Schema::hasTable('websites') && Schema::hasColumn('websites', 'agent_last_failed_at')) {
            Schema::table('websites', fn (Blueprint $table) => $table->dropColumn('agent_last_failed_at'));
        }
    }
};
