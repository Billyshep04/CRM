<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('completed_at');
            $table->index(['due_date', 'status', 'reminder_sent_at'], 'tasks_follow_up_reminder_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_follow_up_reminder_index');
            $table->dropColumn('reminder_sent_at');
        });
    }
};
