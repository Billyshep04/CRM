<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_months')) {
            return;
        }

        Schema::create('subscription_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->date('month_start');
            $table->string('subscription_status')->default('active');
            $table->string('payment_status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'month_start']);
            $table->index(['subscription_id', 'month_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_months');
    }
};
