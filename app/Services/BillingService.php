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
use Illuminate\Support\Facades\DB;

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
        return $this->generateWithSummary($month)['created'];
    }

    /** Previsualización sin escrituras, también para detectar servicios incompletos. */
    public function previewForMonth(CarbonInterface|string $month, ?int $customerId = null): array
    {
        $periodStart = Carbon::parse($month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        app(BillingCycleService::class)->forMonth($periodStart);
        $rows = [];
        $connections = Connection::query()->with(['connectionStatus', 'property.customer.customerStatus']);
        if ($customerId !== null) {
            $connections->whereHas('property', fn ($query) => $query->where('customer_id', $customerId));
        }
        foreach ($connections->get() as $connection) {
            $customer = $connection->property?->customer;
            $existing = Invoice::query()->where('connection_id', $connection->id)->whereDate('period_starts_on', $periodStart)->first();
            $reason = null;
            $amount = null;
            if ($existing !== null) {
                $reason = 'La cuota ya existe; no se duplicará.';

            } elseif (! in_array($connection->connectionStatus?->name, ['ACTIVE', 'ACTIVO'], true)) {
                $reason = 'La conexión no está activa.';
            } elseif (! $connection->property?->active) {
                $reason = 'El predio está inactivo.';
            } elseif (! in_array($customer?->customerStatus?->name, ['ACTIVE', 'ACTIVO', 'EXEMPT', 'EXONERADO'], true)) {
                $reason = 'El titular no está activo.';
            } elseif ($connection->installed_on?->gt($periodEnd)) {
                $reason = 'El mes es anterior a la instalación del servicio.';
            } elseif (! in_array($connection->payment_mode, [Connection::PAYMENT_FIXED, Connection::PAYMENT_METERED], true)) {
                $reason = 'La modalidad de cobro no es válida.';
            } else {
                $usage = $this->usageTypeFor($connection, $periodStart, $periodEnd);
                $rate = $usage === null ? null : $this->rateFor($usage, $periodStart, $periodEnd);
                if ($usage === null) {
                    $reason = 'Falta una asignación de uso vigente para el mes.';
                } elseif ($rate === null) {
                    $reason = 'Falta una tarifa vigente para el uso y año.';
                } else {
                    $amount = $this->serviceAmountFor($connection, $rate, $periodStart, $periodEnd);
                    if ($amount === null) {
                        $reason = $rate->metered_unit_price === null
                            ? 'Falta el precio por m³ de la tarifa.'
                            : 'Faltan lecturas del medidor para el mes.';
                    }
                }
            }
            $rows[] = [
                'connection_id' => $connection->id, 'supply_code' => $connection->supply_code,
                'customer' => $customer?->display_name ?? 'Sin titular',
                'status' => $existing !== null ? 'existing' : ($reason === null ? 'ready' : 'omitted'),
                'reason' => $reason, 'amount' => $amount,
            ];
        }

        return ['month' => $periodStart->format('Y-m'), 'rows' => $rows,
            'ready' => count(array_filter($rows, fn ($row) => $row['status'] === 'ready')),
            'existing' => count(array_filter($rows, fn ($row) => $row['status'] === 'existing')),
            'omitted' => count(array_filter($rows, fn ($row) => $row['status'] === 'omitted'))];
    }

    public function generateWithSummary(CarbonInterface|string $month, ?int $customerId = null): array
    {
        return DB::transaction(function () use ($month, $customerId): array {
            $summary = $this->previewForMonth($month, $customerId);
            $periodStart = Carbon::parse($month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            Setting::query()->where('key', 'billing_period_months')->lockForUpdate()->first();
            $cycle = app(BillingCycleService::class)->forMonth($periodStart);
            $billingPeriod = BillingPeriod::query()->firstOrCreate(['months' => $cycle['months']], ['description' => $cycle['months'].' meses']);
            $created = 0;
            $invoiceIds = [];
            foreach ($summary['rows'] as $row) {
                if ($row['status'] !== 'ready') {
                    continue;
                }
                Connection::query()->whereKey($row['connection_id'])->lockForUpdate()->firstOrFail();
                $snapshot = $this->cycleSnapshot($row['connection_id'], $periodStart, $cycle, $billingPeriod);
                $invoice = Invoice::query()->firstOrCreate(
                    ['connection_id' => $row['connection_id'], 'period_starts_on' => $periodStart->toDateString()],
                    $snapshot + [
                        'issued_on' => $this->issueDate($periodStart)->toDateString(),
                        'period_ends_on' => $periodEnd->toDateString(), 'rate' => $row['amount'],
                        'late_fee' => 0, 'fines' => 0, 'total' => $row['amount'], 'status' => 'PENDING']);
                if ($invoice->wasRecentlyCreated) {
                    $created++;
                    $invoiceIds[] = $invoice->id;
                }
            }

            return $summary + ['created' => $created, 'invoice_ids' => $invoiceIds];
        });
    }

    public function paymentFrequency(): int
    {
        $months = (int) Setting::query()->where('key', 'billing_period_months')->value('value');

        return $months > 0 ? $months : 3;
    }

    public function issueDay(): int
    {
        $day = (int) Setting::query()->where('key', 'billing_issue_day')->value('value');

        return min(28, max(1, $day ?: 28));
    }

    public function graceDeadline(CarbonInterface|string $month, ?int $frequency = null): Carbon
    {
        $periodStart = Carbon::parse($month)->startOfMonth();
        $cycle = $frequency === null
            ? app(BillingCycleService::class)->forMonth($periodStart)
            : app(BillingCycleService::class)->bounds($periodStart, max(1, $frequency));
        $graceMonths = $this->activeLateFeeSetting($cycle['start'])?->grace_months ?? 1;

        return $cycle['end']->copy()->addMonthsNoOverflow((int) $graceMonths)->endOfMonth();
    }

    private function cycleSnapshot(int $connectionId, Carbon $month, array $cycle, BillingPeriod $period): array
    {
        $existing = Invoice::query()->where('connection_id', $connectionId)
            ->whereDate('cycle_starts_on', '<=', $month)->whereDate('cycle_ends_on', '>=', $month)
            ->orderBy('id')->first();
        if ($existing) {
            return $existing->only(['billing_period_id', 'cycle_starts_on', 'cycle_ends_on', 'due_on', 'late_fee_setting_id', 'cycle_late_fee_amount']);
        }
        $fee = $this->activeLateFeeSetting($cycle['start']);

        return [
            'billing_period_id' => $period->id,
            'cycle_starts_on' => $cycle['start']->toDateString(), 'cycle_ends_on' => $cycle['end']->toDateString(),
            'due_on' => $cycle['end']->copy()->addMonthsNoOverflow((int) ($fee?->grace_months ?? 1))->endOfMonth()->toDateString(),
            'late_fee_setting_id' => $fee?->id, 'cycle_late_fee_amount' => $fee?->monthly_amount ?? 0,
        ];
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
            ->whereNull('cycle_starts_on')
            ->where('status', 'PENDING')
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
