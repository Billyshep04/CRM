<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table): void {
            $table->string('assigned_ip', 45)->nullable()->after('primary_domain');
        });

        Schema::table('hosting_packages', function (Blueprint $table): void {
            $table->boolean('shell_access')->nullable()->after('limits');
        });

        Schema::table('website_provisioning_runs', function (Blueprint $table): void {
            $table->text('secrets_encrypted')->nullable()->after('options');
            $table->string('expected_ip', 45)->nullable()->after('secrets_encrypted');
            $table->string('dns_provider')->nullable()->after('expected_ip');
            $table->json('dns_status')->nullable()->after('dns_provider');
            $table->json('ssl_status')->nullable()->after('dns_status');
            $table->timestamp('next_check_at')->nullable()->index()->after('ssl_status');
        });
    }

    public function down(): void
    {
        Schema::table('website_provisioning_runs', fn (Blueprint $table) => $table->dropColumn([
            'secrets_encrypted', 'expected_ip', 'dns_provider', 'dns_status', 'ssl_status', 'next_check_at',
        ]));
        Schema::table('hosting_packages', fn (Blueprint $table) => $table->dropColumn('shell_access'));
        Schema::table('hosting_accounts', fn (Blueprint $table) => $table->dropColumn('assigned_ip'));
    }
};
