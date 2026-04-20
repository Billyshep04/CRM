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
        if (!Schema::hasTable('proposals')) {
            Schema::create('proposals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('parent_proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
                $table->string('proposal_number');
                $table->unsignedInteger('version')->default(1);
                $table->string('title');
                $table->date('issue_date');
                $table->date('expiry_date');
                $table->string('status')->default('draft');
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->decimal('subtotal', 12, 2);
                $table->decimal('total', 12, 2);
                $table->foreignId('pdf_file_id')->nullable()->constrained('files')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['proposal_number', 'version']);
            });
        }

        if (!Schema::hasTable('proposal_line_items')) {
            Schema::create('proposal_line_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
                $table->text('description');
                $table->decimal('quantity', 8, 2)->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('total', 12, 2);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_line_items');
        Schema::dropIfExists('proposals');
    }
};

