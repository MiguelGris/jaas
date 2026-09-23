<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table): void {
            $table->decimal('metered_unit_price', 10, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table): void {
            $table->dropColumn('metered_unit_price');
        });
    }
};
