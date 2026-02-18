<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->text('billing_address');
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->string('disk');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->string('category')->nullable();
                $table->string('checksum', 128)->nullable();
                $table->boolean('is_private')->default(true);
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->nullableMorphs('owner');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('websites')) {
            Schema::create('websites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('name');
                $table->string('login_url');
                $table->text('notes')->nullable();
                $table->text('login_token_encrypted')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description');
                $table->decimal('cost', 12, 2);
                $table->string('status')->default('draft');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('invoiced_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description');
                $table->decimal('monthly_cost', 12, 2);
                $table->string('billing_frequency')->default('monthly');
                $table->date('start_date');
                $table->date('next_invoice_date')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('paused_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('invoice_number')->unique();
                $table->date('issue_date');
                $table->date('due_date');
                $table->string('status')->default('draft');
                $table->decimal('subtotal', 12, 2);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->foreignId('pdf_file_id')->nullable()->constrained('files')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('invoice_line_items')) {
            Schema::create('invoice_line_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->nullableMorphs('billable');
                $table->text('description');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('total', 12, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_preferences')) {
            Schema::create('user_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('theme')->default('light');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('brand_settings')) {
            Schema::create('brand_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('logo_file_id')->nullable()->constrained('files')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_settings');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('websites');
        Schema::dropIfExists('files');
        Schema::dropIfExists('customers');
    }
};
