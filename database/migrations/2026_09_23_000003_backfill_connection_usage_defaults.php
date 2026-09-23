<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $residentialId = DB::table('usage_types')->whereIn('name', ['RESIDENTIAL', 'RESIDENCIAL'])->value('id');

        if ($residentialId === null) {
            return;
        }

        DB::table('connections')->orderBy('id')->each(function (object $connection) use ($residentialId): void {
            if (DB::table('connection_usage_types')->where('connection_id', $connection->id)->exists()) {
                return;
            }

            DB::table('connection_usage_types')->insert([
                'connection_id' => $connection->id,
                'usage_type_id' => $residentialId,
                'starts_on' => $connection->installed_on ?? now()->toDateString(),
                'ends_on' => null,
            ]);
        });
    }

    public function down(): void
    {
        // Defaults added for legacy records are intentionally retained.
    }
};
