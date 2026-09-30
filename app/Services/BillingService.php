<?php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Invoice;
use App\Models\LateFeeSetting;
use App\Models\MeterReading;
use App\Models\Rate;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BillingService
{
    /**
     * Genera una cuota mensual por cada conexión elegible.
     *
     * La combinación conexión/mes es única, por lo que este proceso es
     * idempotente: puede ejecutarse desde el programador o manualmente para
     * recuperar una ejecución fallida sin duplicar recibos.
     */
    public function generateForMonth(CarbonInterface|string $month): int
    {
        $periodStart = Carbon::parse($month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $billingPeriod = BillingPeriod::query()->where('months', $this->paymentFrequency())->first();

        if ($billingPeriod === null) {
            return 0;
        }

        $created = 0;

        $this->eligibleConnections()->each(function (Connection $connection) use ($periodStart, $periodEnd, $billingPeriod, &$created): void {
            $usageTypeId = $this->usageTypeFor($connection, $periodStart, $periodEnd);

            if ($usageTypeId === null) {
                return;
            }

            $rate = $this->rateFor($usageTypeId, $periodStart, $periodEnd);

            if ($rate === null) {
                return;
            }

            $serviceAmount = $this->serviceAmountFor($connection, $rate, $periodStart, $periodEnd);

            // En las conexiones con medidor se necesita por lo menos una
            // lectura del mes y una tarifa por m³ antes de emitir la cuota.
            if ($serviceAmount === null) {
                return;
            }

            $invoice = Invoice::query()->firstOrCreate(
                [
                    'connection_id' => $connection->getKey(),
                    'period_starts_on' => $periodStart->toDateString(),
                ],
                [
                    'billing_period_id' => $billingPeriod->getKey(),
                    'issued_on' => $this->issueDate($periodStart)->toDateString(),
                    'due_on' => $this->graceDeadline($periodStart, (int) $billingPeriod->months)->toDateString(),
                    'period_ends_on' => $periodEnd->toDateString(),
                    'rate' => $serviceAmount,
                    'late_fee' => 0,
                    'fines' => 0,
                    'total' => $serviceAmount,
                    'status' => 'PENDING',
                ],
            );

            if ($invoice->wasRecentlyCreated) {
                $created++;
            }
        });

        return $created;
    }

    public function paymentFrequency(): int
    {
        $months = (int) Setting::query()->where('key', 'billing_period_months')->value('value');

        return in_array($months, [3, 6], true) ? $months : 3;
    }

    public function issueDay(): int
    {
        $day = (int) Setting::query()->where('key', 'billing_issue_day')->value('value');

        return min(28, max(1, $day ?: 28));
    }

    public function graceDeadline(CarbonInterface|string $month, ?int $frequency = null): Carbon
    {
        $periodStart = Carbon::parse($month)->startOfMonth();
        $frequency ??= $this->paymentFrequency();
        $frequency = in_array($frequency, [3, 6], true) ? $frequency : 3;

        // Todas las cuotas del trimestre o semestre comparten el fin del ciclo.
        // El periodo de gracia se agrega después de dicho fin, no después de
        // cada mes individual (enero-marzo vence al terminar abril, por ejemplo).
        $cycleEndMonth = (int) (intdiv($periodStart->month - 1, $frequency) * $frequency) + $frequency;
        $cycleEnd = $periodStart->copy()->month($cycleEndMonth)->endOfMonth();
        $graceMonths = $this->activeLateFeeSetting($periodStart)?->grace_months ?? 1;

        return $cycleEnd->addMonthsNoOverflow(max(1, (int) $graceMonths))->endOfMonth();
    }

    /**
     * Corrige vencimientos creados por versiones anteriores que permitían el
     * desbordamiento de fechas al agregar la gracia (por ejemplo, 31/03 a 01/05).
     */
    public function recalculateDeadlines(): int
    {
        $updated = 0;

        Invoice::query()
            ->with('billingPeriod')
            ->whereNotNull('period_starts_on')
            ->eachById(function (Invoice $invoice) use (&$updated): void {
                $frequency = (int) ($invoice->billingPeriod?->months ?? $this->paymentFrequency());
                $deadline = $this->graceDeadline($invoice->period_starts_on, $frequency);

                if ($invoice->due_on?->toDateString() === $deadline->toDateString()) {
                    return;
                }

                $invoice->forceFill(['due_on' => $deadline->toDateString()])->saveQuietly();
                $updated++;
            });

        return $updated;
    }

    private function issueDate(Carbon $periodStart): Carbon
    {
        return $periodStart->copy()->day($this->issueDay());
    }

    private function eligibleConnections(): Collection
    {
        return Connection::query()
            ->whereIn('payment_mode', [Connection::PAYMENT_FIXED, Connection::PAYMENT_METERED])
            ->whereHas('connectionStatus', fn ($query) => $query->whereIn('name', ['ACTIVE', 'ACTIVO']))
            ->whereHas('property', fn ($query) => $query->where('active', true)
                ->whereHas('customer.customerStatus', fn ($status) => $status->whereIn('name', ['ACTIVE', 'ACTIVO'])))
            ->get();
    }

    private function serviceAmountFor(Connection $connection, Rate $rate, Carbon $periodStart, Carbon $periodEnd): ?float
    {
        if ($connection->payment_mode === Connection::PAYMENT_FIXED) {
            return round((float) $rate->amount, 2);
        }

        if ($rate->metered_unit_price === null) {
            return null;
        }

        $readings = MeterReading::query()
            ->whereHas('meter', fn ($query) => $query->where('connection_id', $connection->getKey()))
            ->whereBetween('read_on', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if (! $readings->exists()) {
            return null;
        }

        // Si hubo un cambio de medidor o más de una lectura dentro del mes,
        // se suman los consumos parciales para no perder volumen facturable.
        return round((float) $readings->sum('consumption') * (float) $rate->metered_unit_price, 2);
    }

    private function usageTypeFor(Connection $connection, Carbon $periodStart, Carbon $periodEnd): ?int
    {
        return $connection->usageAssignments()
            ->whereDate('starts_on', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $periodStart))
            ->orderByDesc('starts_on')
            ->value('usage_type_id');
    }

    private function rateFor(int $usageTypeId, Carbon $periodStart, Carbon $periodEnd): ?Rate
    {
        return Rate::query()
            ->where('usage_type_id', $usageTypeId)
            ->where('year', $periodStart->year)
            ->whereDate('starts_on', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $periodStart))
            ->orderByDesc('starts_on')
            ->first();
    }

    private function activeLateFeeSetting(Carbon $date): ?LateFeeSetting
    {
        return LateFeeSetting::query()
            ->whereDate('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->orderByDesc('starts_on')
            ->first();
    }
}
