<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('email_campaigns', 'last_error')) {
            Schema::table('email_campaigns', function (Blueprint $table): void {
                // Why a scheduled send couldn't start, shown on the campaign until it's fixed.
                $table->text('last_error')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('email_campaigns', 'last_error')) {
            Schema::table('email_campaigns', function (Blueprint $table): void {
                $table->dropColumn('last_error');
            });
        }
    }
};
