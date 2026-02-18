<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('costs')) {
            Schema::create('costs', function (Blueprint $table) {
                $table->id();
                $table->text('description');
                $table->decimal('amount', 12, 2);
                $table->date('incurred_on');
                $table->text('notes')->nullable();
                $table->foreignId('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('costs');
    }
};
