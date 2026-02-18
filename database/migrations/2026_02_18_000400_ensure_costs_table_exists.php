<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('costs')) {
            return;
        }

        $hasFilesTable = Schema::hasTable('files');
        $hasUsersTable = Schema::hasTable('users');

        Schema::create('costs', function (Blueprint $table) use ($hasFilesTable, $hasUsersTable): void {
            $table->id();
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->text('notes')->nullable();

            if ($hasFilesTable) {
                $table->foreignId('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('receipt_file_id')->nullable();
            }

            if ($hasUsersTable) {
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costs');
    }
};
