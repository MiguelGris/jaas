<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->string('payment_mode', 20)->default('FIXED');
            $table->index('payment_mode');
        });

        // Existing connections keep their current fixed-fee billing behaviour.
        DB::table('connections')->update(['payment_mode' => 'FIXED']);

        Schema::table('meters', function (Blueprint $table): void {
            $table->decimal('initial_reading', 10, 2)->default(0);
        });

        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->decimal('previous_reading', 10, 2)->default(0);
            $table->decimal('consumption', 10, 2)->default(0);
            $table->index(['meter_id', 'read_on'], 'meter_readings_meter_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropIndex('meter_readings_meter_date_index');
            $table->dropColumn(['previous_reading', 'consumption']);
        });

        Schema::table('meters', function (Blueprint $table): void {
            $table->dropColumn('initial_reading');
        });

        Schema::table('connections', function (Blueprint $table): void {
            $table->dropIndex(['payment_mode']);
            $table->dropColumn('payment_mode');
        });
    }
};
