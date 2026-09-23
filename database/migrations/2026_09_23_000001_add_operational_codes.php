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
        Schema::create('code_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 50)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::table('users', fn (Blueprint $table) => $table->string('user_code', 20)->nullable()->after('id'));
        Schema::table('customers', fn (Blueprint $table) => $table->string('customer_code', 20)->nullable()->after('id'));
        Schema::table('assemblies', fn (Blueprint $table) => $table->string('assembly_code', 20)->nullable()->after('id'));
        Schema::table('invoices', fn (Blueprint $table) => $table->string('invoice_code', 20)->nullable()->after('id'));
        Schema::table('payments', fn (Blueprint $table) => $table->string('receipt_code', 20)->nullable()->after('id'));
        Schema::table('fines', fn (Blueprint $table) => $table->string('fine_code', 20)->nullable()->after('id'));
        Schema::table('incomes', fn (Blueprint $table) => $table->string('income_code', 20)->nullable()->after('id'));
        Schema::table('expenses', fn (Blueprint $table) => $table->string('expense_code', 20)->nullable()->after('id'));

        $this->backfill('users', 'user_code', 'USR', 4, null, 'users');
        $this->backfill('customers', 'customer_code', 'CLI', 4, null, 'customers');
        $this->backfill('properties', 'property_code', 'PRD', 4, null, 'properties');
        $this->backfill('connections', 'supply_code', 'SUM', 4, null, 'connections');
        $this->backfill('assemblies', 'assembly_code', 'ASM', 4, 'held_on', 'assemblies');
        $this->backfill('invoices', 'invoice_code', 'FAC', 6, 'issued_on', 'invoices');
        $this->backfill('payments', 'receipt_code', 'RC', 6, 'paid_at', 'payments');
        $this->backfill('fines', 'fine_code', 'MLT', 6, 'generated_on', 'fines');
        $this->backfill('incomes', 'income_code', 'ING', 6, 'received_on', 'incomes');
        $this->backfill('expenses', 'expense_code', 'EGR', 6, 'incurred_on', 'expenses');

        Schema::table('users', fn (Blueprint $table) => $table->unique('user_code'));
        Schema::table('customers', fn (Blueprint $table) => $table->unique('customer_code'));
        Schema::table('properties', fn (Blueprint $table) => $table->unique('property_code'));
        Schema::table('assemblies', fn (Blueprint $table) => $table->unique('assembly_code'));
        Schema::table('invoices', fn (Blueprint $table) => $table->unique('invoice_code'));
        Schema::table('payments', fn (Blueprint $table) => $table->unique('receipt_code'));
        Schema::table('fines', fn (Blueprint $table) => $table->unique('fine_code'));
        Schema::table('incomes', fn (Blueprint $table) => $table->unique('income_code'));
        Schema::table('expenses', fn (Blueprint $table) => $table->unique('expense_code'));
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropUnique(['expense_code']));
        Schema::table('incomes', fn (Blueprint $table) => $table->dropUnique(['income_code']));
        Schema::table('fines', fn (Blueprint $table) => $table->dropUnique(['fine_code']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique(['receipt_code']));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropUnique(['invoice_code']));
        Schema::table('assemblies', fn (Blueprint $table) => $table->dropUnique(['assembly_code']));
        Schema::table('properties', fn (Blueprint $table) => $table->dropUnique(['property_code']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropUnique(['customer_code']));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['user_code']));

        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('expense_code'));
        Schema::table('incomes', fn (Blueprint $table) => $table->dropColumn('income_code'));
        Schema::table('fines', fn (Blueprint $table) => $table->dropColumn('fine_code'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('receipt_code'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('invoice_code'));
        Schema::table('assemblies', fn (Blueprint $table) => $table->dropColumn('assembly_code'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('customer_code'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('user_code'));
        Schema::dropIfExists('code_sequences');
    }

    private function backfill(string $table, string $field, string $prefix, int $padding, ?string $dateField, string $series): void
    {
        $counters = [];
        $records = DB::table($table)
            ->select(['id', ...($dateField === null ? [] : [$dateField])])
            ->where(function ($query) use ($field): void {
                $query->whereNull($field)->orWhere($field, '');
            })
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $year = $dateField === null ? null : Carbon::parse($record->{$dateField} ?? now())->format('y');
            $key = $series.($year === null ? '' : ':'.$year);
            $number = ($counters[$key] ?? 0) + 1;
            $counters[$key] = $number;
            $code = $year === null
                ? $prefix.'_'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT)
                : $prefix.$year.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            DB::table($table)->where('id', $record->id)->update([$field => $code]);
        }

        if ($dateField === null && ! array_key_exists($series, $counters)) {
            $existingRecords = (int) DB::table($table)->count();

            if ($existingRecords > 0) {
                $counters[$series] = $existingRecords;
            }
        }

        foreach ($counters as $key => $lastValue) {
            DB::table('code_sequences')->insert([
                'series' => $key,
                'last_value' => $lastValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
