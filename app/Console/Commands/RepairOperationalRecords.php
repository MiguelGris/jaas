<?php

namespace App\Console\Commands;

use App\Models\Assembly;
use App\Models\Connection;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOperationalRecords extends Command
{
    protected $signature = 'jass:repair-operational-records {--apply : Aplica las reparaciones y conserva su bitácora} {--user= : ID del administrador responsable}';

    protected $description = 'Revisa usos idénticos duplicados y multas pendientes de asambleas abiertas o canceladas';

    public function handle(AuditService $audit): int
    {
        $actor = User::query()->whereKey($this->option('user'))->where('active', true)->whereHas('role', fn ($query) => $query->whereIn('name', ['ADMINISTRATOR', 'ADMINISTRADOR']))->first();
        if ($this->option('apply') && $actor === null) {
            $this->error('Indica --user con el ID de un administrador activo para registrar la reparación en la bitácora.');

            return self::FAILURE;
        }
        $duplicates = 0;
        $cancelled = 0;
        $paidAssemblies = 0;
        Connection::query()->orderBy('id')->eachById(function ($connection) use ($audit, $actor, &$duplicates): void {
            DB::transaction(function () use ($connection, $audit, $actor, &$duplicates): void {
                $connection = Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
                $seen = [];
                foreach ($connection->usageAssignments()->orderBy('id')->get() as $usage) {
                    $key = json_encode([$usage->usage_type_id, $usage->starts_on?->toDateString(), $usage->ends_on?->toDateString()]);
                    if (isset($seen[$key])) {
                        $duplicates++;
                        if ($this->option('apply')) {
                            $before = $audit->snapshot($usage);
                            $usage->delete();
                            $audit->deleted($actor, $usage, $before);
                        }
                    } else {
                        $seen[$key] = true;
                    }
                }
            }, 3);
        });
        Assembly::query()->where('status', '!=', 'HELD')->orderBy('id')->eachById(function ($assembly) use ($audit, $actor, &$cancelled, &$paidAssemblies): void {
            DB::transaction(function () use ($assembly, $audit, $actor, &$cancelled, &$paidAssemblies): void {
                $assembly = Assembly::query()->whereKey($assembly->id)->lockForUpdate()->firstOrFail();
                if ($assembly->status === 'HELD') {
                    return;
                }
                if ($assembly->fines()->whereHas('paymentAllocations')->exists()) {
                    $paidAssemblies++;
                }
                foreach ($assembly->fines()->where('status', 'PENDING')->whereDoesntHave('paymentAllocations')->lockForUpdate()->get() as $fine) {
                    $cancelled++;
                    if ($this->option('apply')) {
                        $before = $audit->snapshot($fine);
                        $fine->update(['status' => 'CANCELLED']);
                        $audit->updated($actor, $fine, $before);
                    }
                }
            }, 3);
        });
        $this->info(($this->option('apply') ? 'Reparación aplicada' : 'Vista previa').": usos duplicados {$duplicates}; multas pendientes {$cancelled}.");
        if ($paidAssemblies > 0) {
            $this->warn("Asambleas abiertas o canceladas con cobros históricos: {$paidAssemblies}. Revisar sus recibos; no se modificaron esos pagos.");
        }

        return self::SUCCESS;
    }
}
