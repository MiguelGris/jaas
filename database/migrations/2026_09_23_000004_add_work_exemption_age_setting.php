<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'work_exemption_age'],
            [
                'value' => '65',
                'description' => 'Edad mínima para exonerar al titular de faenas comunitarias.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'work_exemption_age')->delete();
    }
};
