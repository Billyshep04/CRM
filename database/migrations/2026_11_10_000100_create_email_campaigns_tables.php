<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->string('preview_text')->nullable();
                $table->string('from_name')->nullable();
                $table->string('from_email')->nullable();
                $table->string('reply_to')->nullable();
                // Stored and sent exactly as pasted; only merge tags are replaced at send time.
                $table->longText('html_body')->nullable();
                $table->string('status')->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('unsubscribed_count')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (!Schema::hasTable('email_campaign_list')) {
            Schema::create('email_campaign_list', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
                $table->foreignId('email_list_id')->constrained('email_lists')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['email_campaign_id', 'email_list_id']);
            });
        }

        // Every send attempt, including test sends, with its outcome.
        if (!Schema::hasTable('email_send_attempts')) {
            Schema::create('email_send_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('email_campaign_id')->nullable()->constrained('email_campaigns')->nullOnDelete();
                $table->string('type');
                $table->string('email');
                $table->string('status');
                $table->text('error')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->index(['email_campaign_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_attempts');
        Schema::dropIfExists('email_campaign_list');
        Schema::dropIfExists('email_campaigns');
    }
};
