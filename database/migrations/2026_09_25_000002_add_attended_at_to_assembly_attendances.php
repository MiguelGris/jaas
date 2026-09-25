<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assembly_attendances', function (Blueprint $table): void {
            $table->timestamp('attended_at')->nullable()->after('attended');
        });
    }

    public function down(): void
    {
        Schema::table('assembly_attendances', function (Blueprint $table): void {
            $table->dropColumn('attended_at');
        });
    }
};
