<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            return;
        }

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assigned_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('hours')->default(0);
            $table->unsignedTinyInteger('minutes')->default(0);
            $table->text('staff_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['job_id', 'status']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
