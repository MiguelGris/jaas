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
        $payments = DB::table('payments')
            ->select(['id', 'paid_at', 'operation_number'])
            ->orderBy('id')
            ->get();
        $used = [];
        $needsGeneratedNumber = [];
        $counters = [];

        foreach ($payments as $payment) {
            $operation = trim((string) ($payment->operation_number ?? ''));
            if ($operation === '' || isset($used[$operation])) {
                $needsGeneratedNumber[(int) $payment->id] = true;
                continue;
            }

            $used[$operation] = true;
            if (preg_match('/^OP(\d{2})-(\d+)$/', $operation, $matches) === 1) {
                $counters[$matches[1]] = max($counters[$matches[1]] ?? 0, (int) $matches[2]);
            }
        }

        foreach ($payments as $payment) {
            if (! isset($needsGeneratedNumber[(int) $payment->id])) {
                continue;
            }

            $year = Carbon::parse($payment->paid_at)->format('y');
            do {
                $number = ($counters[$year] ?? 0) + 1;
                $counters[$year] = $number;
                $operation = 'OP'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            } while (isset($used[$operation]));

            DB::table('payments')->where('id', $payment->id)->update(['operation_number' => $operation]);
            $used[$operation] = true;
        }

        foreach ($counters as $year => $lastValue) {
            DB::table('code_sequences')->updateOrInsert(
                ['series' => 'payment_operations:'.$year],
                ['last_value' => $lastValue, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('operation_number', 'payments_operation_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_operation_number_unique');
        });
    }
};
