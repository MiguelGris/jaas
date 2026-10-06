<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cycle_schedules', function (Blueprint $table): void {
            $table->id();
            $table->date('starts_on')->unique();
            $table->date('anchor_on')->nullable();
            $table->unsignedInteger('months');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->date('cycle_starts_on')->nullable();
            $table->date('cycle_ends_on')->nullable();
            $table->foreignId('late_fee_setting_id')->nullable()->constrained('late_fee_settings')->restrictOnDelete();
            $table->decimal('cycle_late_fee_amount', 10, 2)->nullable();
            $table->index(['connection_id', 'cycle_starts_on']);
        });
        // Snapshot only: never rewrite paid amounts, allocation history, or due dates.
        DB::table('invoices')->orderBy('id')->eachById(function ($invoice): void {
            $months = max(1, (int) DB::table('billing_periods')->where('id', $invoice->billing_period_id)->value('months'));
            $date = Carbon::parse($invoice->period_starts_on)->startOfMonth();
            $distance = ($date->year - 2026) * 12 + $date->month - 1;
            $start = Carbon::parse('2026-01-01')->addMonthsNoOverflow((int) floor($distance / $months) * $months);
            $fee = DB::table('late_fee_settings')->where('starts_on', '<=', $start->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $start->toDateString()))->orderByDesc('starts_on')->first();
            DB::table('invoices')->where('id', $invoice->id)->update([
                'cycle_starts_on' => $start->toDateString(), 'cycle_ends_on' => $start->copy()->addMonthsNoOverflow($months)->subDay()->toDateString(),
                'late_fee_setting_id' => $fee?->id, 'cycle_late_fee_amount' => $fee?->monthly_amount ?? 0,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['late_fee_setting_id']);
            $table->dropIndex(['connection_id', 'cycle_starts_on']);
            $table->dropColumn(['cycle_starts_on', 'cycle_ends_on', 'late_fee_setting_id', 'cycle_late_fee_amount']);
        });
        Schema::dropIfExists('billing_cycle_schedules');
    }
};
