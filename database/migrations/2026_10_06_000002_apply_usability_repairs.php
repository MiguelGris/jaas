<?php

use App\Services\UserCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->string('external_reference', 100)->nullable());
        app(UserCodeService::class)->repairMissing();
        if (DB::getDriverName() === 'mysql') {
            // Laravel services are the only supported source of fines and live debt.
            DB::unprepared('DROP PROCEDURE IF EXISTS generate_assembly_fines');
            DB::unprepared('DROP VIEW IF EXISTS vw_delinq_customers');
            DB::unprepared('DROP VIEW IF EXISTS vw_customer_account');
        }
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('external_reference'));
        // Generated account codes and retired unsafe objects are intentionally not restored.
    }
};
