<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_lists')) {
            Schema::create('email_lists', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // One row per email address, shared by every list. Not linked to CRM customers.
        if (!Schema::hasTable('email_subscribers')) {
            Schema::create('email_subscribers', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('status')->default('subscribed');
                $table->string('consent_source');
                $table->text('consent_note')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamp('bounced_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (!Schema::hasTable('email_list_subscriber')) {
            Schema::create('email_list_subscriber', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('email_list_id')->constrained('email_lists')->cascadeOnDelete();
                $table->foreignId('email_subscriber_id')->constrained('email_subscribers')->cascadeOnDelete();
                $table->string('added_via');
                $table->timestamps();

                $table->unique(['email_list_id', 'email_subscriber_id']);
            });
        }

        // Global do-not-email list. Kept even if the subscriber row is deleted, and only
        // lifted by an admin resubscribing the address with a note.
        if (!Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->index();
                $table->string('reason');
                $table->string('source')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('lifted_at')->nullable();
                $table->foreignId('lifted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('lift_note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('email_list_subscriber');
        Schema::dropIfExists('email_subscribers');
        Schema::dropIfExists('email_lists');
    }
};
