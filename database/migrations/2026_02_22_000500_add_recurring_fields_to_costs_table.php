<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('costs')) {
            return;
        }

        Schema::table('costs', function (Blueprint $table): void {
            if (!Schema::hasColumn('costs', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('incurred_on');
            }
            if (!Schema::hasColumn('costs', 'recurring_frequency')) {
                $table->string('recurring_frequency', 20)->nullable()->after('is_recurring');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('costs')) {
            return;
        }

        Schema::table('costs', function (Blueprint $table): void {
            if (Schema::hasColumn('costs', 'recurring_frequency')) {
                $table->dropColumn('recurring_frequency');
            }
            if (Schema::hasColumn('costs', 'is_recurring')) {
                $table->dropColumn('is_recurring');
            }
        });
    }
};
