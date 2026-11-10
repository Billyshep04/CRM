<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A snapshot of who a campaign went to, taken when it's sent.
        if (!Schema::hasTable('email_campaign_recipients')) {
            Schema::create('email_campaign_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
                $table->foreignId('email_subscriber_id')->nullable()->constrained('email_subscribers')->nullOnDelete();
                $table->string('email');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                // Only a hash of each person's unsubscribe / view-online token is stored.
                $table->string('token_hash', 64)->nullable()->unique();
                $table->string('status')->default('pending');
                $table->text('error')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('provider_message_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->unique(['email_campaign_id', 'email']);
                $table->index(['email_campaign_id', 'status']);
            });
        }

        if (!Schema::hasColumn('email_send_attempts', 'email_campaign_recipient_id')) {
            Schema::table('email_send_attempts', function (Blueprint $table): void {
                $table->foreignId('email_campaign_recipient_id')->nullable()->after('email_campaign_id')
                    ->constrained('email_campaign_recipients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('email_send_attempts', 'email_campaign_recipient_id')) {
            Schema::table('email_send_attempts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('email_campaign_recipient_id');
            });
        }
        Schema::dropIfExists('email_campaign_recipients');
    }
};
