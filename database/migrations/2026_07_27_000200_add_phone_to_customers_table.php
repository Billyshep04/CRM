<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'phone')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('phone', 50)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'phone')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropColumn('phone');
            });
        }
    }
};
