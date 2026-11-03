<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_reports')) {
            return;
        }

        Schema::create('social_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_number');
            $table->date('report_month');
            $table->date('issue_date');
            $table->json('data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'report_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_reports');
    }
};
