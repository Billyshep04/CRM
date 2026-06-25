<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proposals')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table): void {
            if (!Schema::hasColumn('proposals', 'proposal_type')) {
                $table->string('proposal_type')->nullable()->after('title');
            }

            if (!Schema::hasColumn('proposals', 'proposal_type_label')) {
                $table->string('proposal_type_label')->nullable()->after('proposal_type');
            }

            if (!Schema::hasColumn('proposals', 'form_answers')) {
                $table->json('form_answers')->nullable()->after('proposal_type_label');
            }
        });

        DB::table('proposals')
            ->where('status', 'sent')
            ->update(['status' => 'pending']);

        DB::table('proposals')
            ->where('status', 'accepted')
            ->update(['status' => 'approved']);

        DB::table('proposals')
            ->where('status', 'rejected')
            ->update(['status' => 'declined']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('proposals')) {
            return;
        }

        DB::table('proposals')
            ->where('status', 'pending')
            ->update(['status' => 'sent']);

        DB::table('proposals')
            ->where('status', 'approved')
            ->update(['status' => 'accepted']);

        DB::table('proposals')
            ->where('status', 'declined')
            ->update(['status' => 'rejected']);

        Schema::table('proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('proposals', 'form_answers')) {
                $table->dropColumn('form_answers');
            }

            if (Schema::hasColumn('proposals', 'proposal_type_label')) {
                $table->dropColumn('proposal_type_label');
            }

            if (Schema::hasColumn('proposals', 'proposal_type')) {
                $table->dropColumn('proposal_type');
            }
        });
    }
};
