<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->string('concept', 20)->nullable();
            $table->index('payment_id', 'payment_allocations_payment_lookup');
            $table->dropUnique(['payment_id', 'invoice_id']);
            $table->unique(['payment_id', 'invoice_id', 'concept'], 'payment_allocations_invoice_concept_unique');
        });
        Schema::create('late_fee_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 250);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fee_waivers');
        // Restore the old one-allocation-per-invoice layout on explicit rollback.
        $duplicates = DB::table('payment_allocations')->whereNotNull('invoice_id')
            ->select('payment_id', 'invoice_id')->groupBy('payment_id', 'invoice_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($duplicates as $duplicate) {
            $query = DB::table('payment_allocations')->where('payment_id', $duplicate->payment_id)->where('invoice_id', $duplicate->invoice_id);
            $first = (clone $query)->min('id');
            $amount = (clone $query)->sum('amount');
            (clone $query)->where('id', $first)->update(['amount' => $amount]);
            (clone $query)->where('id', '!=', $first)->delete();
        }
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropUnique('payment_allocations_invoice_concept_unique');
            $table->unique(['payment_id', 'invoice_id']);
            $table->dropIndex('payment_allocations_payment_lookup');
            $table->dropColumn('concept');
        });
    }
};
