<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_line_items')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE invoice_line_items MODIFY quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00'
            );
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoice_line_items ALTER COLUMN quantity TYPE NUMERIC(8,2)');
            DB::statement('ALTER TABLE invoice_line_items ALTER COLUMN quantity SET DEFAULT 1.00');
            return;
        }

        // Fallback for other drivers using generic schema operations.
        DB::statement('UPDATE invoice_line_items SET quantity = ROUND(quantity, 2)');
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoice_line_items')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE invoice_line_items MODIFY quantity UNSIGNED INT NOT NULL DEFAULT 1'
            );
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoice_line_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE invoice_line_items ALTER COLUMN quantity SET DEFAULT 1');
        }
    }
};
