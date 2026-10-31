<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('seo_reports', 'sent_at')) {
            return;
        }

        Schema::table('seo_reports', function (Blueprint $table): void {
            $table->timestamp('sent_at')->nullable()->after('data');
            $table->string('sent_to')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('seo_reports', function (Blueprint $table): void {
            $table->dropColumn(['sent_at', 'sent_to']);
        });
    }
};
