<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class GenerateMonthlyCharges extends Command
{
    protected $signature = 'billing:generate-monthly {--month= : Mes a emitir en formato AAAA-MM}';

    protected $description = 'Genera las cuotas mensuales de los suministros activos';

    public function handle(BillingService $billing): int
    {
        $month = $this->option('month');

        if ($month !== null && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('El mes debe usar el formato AAAA-MM.');

            return self::INVALID;
        }

        // La opción permite recuperar un mes omitido. Sin ella, el programador
        // siempre trabaja con el mes actual y el servicio evita duplicados.
        $period = Carbon::parse($month ?? now())->startOfMonth();
        $created = $billing->generateForMonth($period);

        $this->info("Cuotas generadas para {$period->translatedFormat('F Y')}: {$created}.");

        return self::SUCCESS;
    }
}
