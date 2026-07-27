<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jobs', 'archived_at')) {
            Schema::table('jobs', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('invoiced_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jobs', 'archived_at')) {
            Schema::table('jobs', function (Blueprint $table): void {
                $table->dropColumn('archived_at');
            });
        }
    }
};
