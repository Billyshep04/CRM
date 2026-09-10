<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'payment_link')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('payment_link', 2048)->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'payment_link')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('payment_link');
            });
        }
    }
};
