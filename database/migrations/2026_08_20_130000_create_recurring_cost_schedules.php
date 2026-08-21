<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_costs', function (Blueprint $table): void {
            $table->id();
            $table->text('description');
            $table->string('frequency', 20)->default('monthly');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recurring_cost_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_cost_id')->constrained('recurring_costs')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['recurring_cost_id', 'effective_from']);
        });

        if (! Schema::hasTable('costs')) {
            return;
        }

        DB::table('costs')->whereNull('deleted_at')->where('is_recurring', true)->orderBy('id')->get()->each(function ($cost): void {
            $scheduleId = DB::table('recurring_costs')->insertGetId([
                'description' => $cost->description,
                'frequency' => in_array($cost->recurring_frequency, ['monthly', 'annual'], true) ? $cost->recurring_frequency : 'monthly',
                'starts_on' => $cost->incurred_on,
                'active' => true,
                'notes' => $cost->notes,
                'receipt_file_id' => $cost->receipt_file_id,
                'created_by_user_id' => $cost->created_by_user_id,
                'created_at' => $cost->created_at ?? now(),
                'updated_at' => $cost->updated_at ?? now(),
            ]);
            DB::table('recurring_cost_rates')->insert([
                'recurring_cost_id' => $scheduleId,
                'amount' => $cost->amount,
                'effective_from' => $cost->incurred_on,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('costs')->where('id', $cost->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_cost_rates');
        Schema::dropIfExists('recurring_costs');
    }
};
