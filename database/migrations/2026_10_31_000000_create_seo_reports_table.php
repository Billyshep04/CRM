<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_reports')) {
            return;
        }

        Schema::create('seo_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_number');
            $table->date('report_month');
            $table->date('issue_date');
            $table->string('website_domain');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'report_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_reports');
    }
};
