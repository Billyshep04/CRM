<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('businesses') || !Schema::hasColumn('businesses', 'contacted_by_user_id')) {
            return;
        }

        $needsAttribution = DB::table('businesses')
            ->whereNotNull('contacted_at')
            ->whereNull('contacted_by_user_id')
            ->exists();

        if (!$needsAttribution) {
            return;
        }

        $adminUsers = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', 'admin');

        $billyAdminId = (clone $adminUsers)
            ->whereRaw('LOWER(users.name) LIKE ?', ['billy%'])
            ->orderBy('users.id')
            ->value('users.id');

        if (!$billyAdminId) {
            $adminIds = (clone $adminUsers)
                ->distinct()
                ->pluck('users.id');

            if ($adminIds->count() === 1) {
                $billyAdminId = $adminIds->first();
            }
        }

        if (!$billyAdminId) {
            throw new RuntimeException('Cannot safely attribute existing contacted leads: Billy could not be uniquely identified as an admin.');
        }

        DB::table('businesses')
            ->whereNotNull('contacted_at')
            ->whereNull('contacted_by_user_id')
            ->update(['contacted_by_user_id' => $billyAdminId]);
    }

    public function down(): void
    {
        // Keep historical attribution intact; clearing it on rollback would lose audit data.
    }
};
