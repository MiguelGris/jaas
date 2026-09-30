<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table): void {
            // MySQL necesita conservar un índice cuyo primer campo sostenga la
            // clave foránea mientras se retira la unicidad anual anterior.
            $table->index('usage_type_id', 'rates_usage_type_id_index');
        });

        Schema::table('rates', function (Blueprint $table): void {
            $table->dropUnique(['usage_type_id', 'year']);
            $table->index(['usage_type_id', 'year', 'starts_on'], 'rates_usage_year_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table): void {
            $table->unique(['usage_type_id', 'year']);
        });

        Schema::table('rates', function (Blueprint $table): void {
            $table->dropIndex('rates_usage_year_start_index');
            $table->dropIndex('rates_usage_type_id_index');
        });
    }
};
