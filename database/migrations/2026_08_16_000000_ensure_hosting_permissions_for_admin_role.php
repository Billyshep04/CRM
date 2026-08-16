<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'hosting_view' => ['View hosting', 'View hosting accounts and status.'],
        'hosting_manage' => ['Manage hosting', 'Configure and synchronise hosting.'],
        'hosting_provision' => ['Provision hosting', 'Create hosting accounts and websites.'],
        'hosting_credentials' => ['Manage hosting credentials', 'Manage encrypted hosting provider credentials.'],
        'hosting_terminate' => ['Terminate hosting', 'Reserved for explicitly authorised termination workflows.'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $slug => [$name, $description]) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', $slug)->update([
                'name' => $name,
                'description' => $description,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Do not remove production permissions or role assignments during rollback.
    }
};
