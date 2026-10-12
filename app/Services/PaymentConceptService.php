<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PaymentConceptService
{
    /**
     * Construye los conceptos legibles que componen un recibo.
     *
     * @return list<array{category: string, concept: string, detail: string, amount: float}>
     */
    public function forPayment(Payment $payment): array
    {
        $payment->loadMissing(['allocations.invoice', 'allocations.fine.assembly']);
        $rows = collect();

        foreach ($payment->allocations as $allocation) {
            if ($allocation->fine !== null || $allocation->charge_type === 'FINE') {
                $rows->push([
                    'category' => 'Multa',
                    'concept' => $allocation->fine?->fine_code ?? 'Multa histórica',
                    'detail' => $allocation->fine?->reason ?: 'Multa registrada',
                    'amount' => round((float) $allocation->amount, 2),
                ]);

                continue;
            }

            if ($allocation->invoice === null) {
                $rows->push([
                    'category' => 'Servicio',
                    'concept' => 'Pago registrado',
                    'detail' => 'Distribución histórica',
                    'amount' => round((float) $allocation->amount, 2),
                ]);

                continue;
            }

            $invoice = $allocation->invoice;
            $period = $invoice->period_starts_on?->format('m/Y') ?? 'Periodo no indicado';
            $parts = $this->partsForAllocation(
                $allocation,
                (float) $allocation->amount,
                $this->previousInvoiceAllocation($allocation, $payment),
            );

            if ($parts['services'] > 0) {
                $rows->push([
                    'category' => 'Servicio',
                    'concept' => $invoice->invoice_code,
                    'detail' => 'Cuota de servicio '.$period,
                    'amount' => $parts['services'],
                ]);
            }
            if ($parts['fines'] > 0) {
                $rows->push([
                    'category' => 'Multa',
                    'concept' => $invoice->invoice_code,
                    'detail' => 'Multa incluida en la cuota '.$period,
                    'amount' => $parts['fines'],
                ]);
            }
            if ($parts['late_fees'] > 0) {
                $rows->push([
                    'category' => 'Mora',
                    'concept' => $invoice->invoice_code,
                    'detail' => 'Mora del ciclo '.$invoice->cycleLabel(),
                    'amount' => $parts['late_fees'],
                ]);
            }
        }

        if ($rows->isEmpty()) {
            $rows->push([
                'category' => 'Servicio',
                'concept' => 'Pago registrado',
                'detail' => 'Sin distribución detallada',
                'amount' => round((float) $payment->amount, 2),
            ]);
        }

        return $rows->values()->all();
    }

    /**
     * @return array{services: float, fines: float, late_fees: float}
     */
    public function totals(CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): array
    {
        $start = Carbon::parse($startsAt)->startOfDay();
        $end = Carbon::parse($endsAt)->endOfDay();
        $totals = $this->emptyTotals();
        $invoiceOffsets = [];

        foreach ($this->allocationsThrough($end) as $allocation) {
            $parts = $this->allocationParts($allocation, $invoiceOffsets);

            if ($allocation->payment->paid_at->lt($start)) {
                continue;
            }

            $this->add($totals, $parts);
        }

        $totals['services'] += (float) Payment::query()
            ->active()
            ->whereBetween('paid_at', [$start, $end])
            ->whereDoesntHave('allocations')
            ->sum('amount');

        return $this->rounded($totals);
    }

    /**
     * @return array<int, array{services: float, fines: float, late_fees: float}>
     */
    public function totalsByMonth(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear()->endOfDay();
        $months = array_fill(1, 12, null);

        foreach ($months as $month => $_value) {
            $months[$month] = $this->emptyTotals();
        }

        $invoiceOffsets = [];
        foreach ($this->allocationsThrough($end) as $allocation) {
            $parts = $this->allocationParts($allocation, $invoiceOffsets);
            $paidAt = $allocation->payment->paid_at;

            if ($paidAt->lt($start)) {
                continue;
            }

            $this->add($months[$paidAt->month], $parts);
        }

        Payment::query()
            ->active()
            ->whereBetween('paid_at', [$start, $end])
            ->whereDoesntHave('allocations')
            ->get()
            ->each(function (Payment $payment) use (&$months): void {
                $months[$payment->paid_at->month]['services'] += (float) $payment->amount;
            });

        foreach ($months as $month => $totals) {
            $months[$month] = $this->rounded($totals);
        }

        return $months;
    }

    /**
     * @return Collection<int, PaymentAllocation>
     */
    private function allocationsThrough(CarbonInterface $end): Collection
    {
        // Se procesa en orden cronológico para repartir correctamente pagos
        // parciales sucesivos entre servicio, multa incluida y mora.
        return PaymentAllocation::query()
            ->whereHas('payment', fn ($query) => $query->active()->where('paid_at', '<=', $end))
            ->with(['payment', 'invoice', 'fine'])
            ->get()
            ->sortBy(fn (PaymentAllocation $allocation): string => sprintf(
                '%s-%012d-%012d',
                $allocation->payment->paid_at->format('YmdHis.u'),
                $allocation->payment_id,
                $allocation->getKey(),
            ))
            ->values();
    }

    /**
     * @param  array<int, float>  $invoiceOffsets
     * @return array{services: float, fines: float, late_fees: float}
     */
    private function allocationParts(PaymentAllocation $allocation, array &$invoiceOffsets): array
    {
        $amount = (float) $allocation->amount;

        if ($allocation->fine_id !== null || $allocation->charge_type === 'FINE') {
            return ['services' => 0.0, 'fines' => $amount, 'late_fees' => 0.0];
        }

        if ($allocation->invoice === null) {
            return ['services' => $amount, 'fines' => 0.0, 'late_fees' => 0.0];
        }

        $invoiceId = (int) $allocation->invoice_id;
        $offset = $invoiceOffsets[$invoiceId] ?? 0.0;
        $parts = $this->partsForAllocation($allocation, $amount, $offset);
        $invoiceOffsets[$invoiceId] = round($offset + ($allocation->concept === 'LATE_FEE' ? 0 : $amount), 2);

        return $parts;
    }

    private function partsForAllocation(PaymentAllocation $allocation, float $amount, float $offset, ?Invoice $invoice = null): array
    {
        if ($allocation->concept === 'SERVICE' && $allocation->service_component !== null) {
            return ['services' => (float) $allocation->service_component, 'fines' => (float) $allocation->fine_component, 'late_fees' => 0.0];
        }
        if ($allocation->concept === 'LATE_FEE') {
            return ['services' => 0.0, 'fines' => 0.0, 'late_fees' => $amount];
        }

        return $this->splitInvoiceAmount($invoice ?? $allocation->invoice, $amount, $offset);
    }

    /** Historical combined payments retain their original distribution. */
    public function paidPartsForInvoice(Invoice $invoice): array
    {
        $totals = $this->emptyTotals();
        $offset = 0.0;
        $settledAt = null;
        $coverageEvents = collect();
        $allocations = ($invoice->relationLoaded('paymentAllocations') ? $invoice->getRelation('paymentAllocations')->loadMissing('payment') : $invoice->paymentAllocations()->with('payment')->get())
            ->sortBy(fn ($row) => sprintf('%s-%012d-%012d', $row->payment->paid_at->format('YmdHis.u'), $row->payment_id, $row->id));
        foreach ($allocations as $allocation) {
            $allocation->setRelation('invoice', $invoice);
            $parts = $this->partsForAllocation($allocation, (float) $allocation->amount, $offset, $invoice);
            $this->add($totals, $parts);
            if ($allocation->concept !== 'LATE_FEE') {
                $offset += (float) $allocation->amount;
            }
            $coverageEvents->push(['date' => $allocation->payment->paid_at, 'amount' => $parts['services']]);
        }
        $waivers = $invoice->relationLoaded('serviceWaivers') ? $invoice->getRelation('serviceWaivers') : $invoice->serviceWaivers()->get();
        foreach ($waivers as $waiver) {
            $coverageEvents->push(['date' => $waiver->created_at, 'amount' => (float) $waiver->amount]);
        }
        $covered = 0.0;
        foreach ($coverageEvents->sortBy(fn ($event) => $event['date']->format('YmdHis.u')) as $event) {
            $covered = round($covered + $event['amount'], 2);
            if ($settledAt === null && $covered >= round((float) $invoice->rate, 2)) {
                $settledAt = $event['date'];
            }
        }

        return $this->rounded($totals) + ['service_settled_at' => $settledAt, 'service_waived' => round((float) $waivers->sum('amount'), 2)];
    }

    private function previousInvoiceAllocation(PaymentAllocation $allocation, Payment $payment): float
    {
        if ($allocation->invoice_id === null) {
            return 0.0;
        }

        return round((float) PaymentAllocation::query()
            ->where('invoice_id', $allocation->invoice_id)
            ->where(fn ($query) => $query->whereNull('concept')->orWhere('concept', '!=', 'LATE_FEE'))
            ->whereHas('payment', function ($query) use ($payment): void {
                $query->active()->where(function ($before) use ($payment): void {
                    $before->where('paid_at', '<', $payment->paid_at)
                        ->orWhere(function ($sameTime) use ($payment): void {
                            $sameTime->where('paid_at', $payment->paid_at)
                                ->where('id', '<', $payment->getKey());
                        });
                });
            })
            ->sum('amount'), 2);
    }

    /**
     * @return array{services: float, fines: float, late_fees: float}
     */
    private function splitInvoiceAmount(Invoice $invoice, float $amount, float $offset): array
    {
        // Una cuota se interpreta como segmentos consecutivos. El desplazamiento
        // indica cuánto cubrieron pagos anteriores y evita contar dos veces un
        // mismo concepto en recibos o reportes.
        $allocationStart = max(0.0, $offset);
        $allocationEnd = $allocationStart + max(0.0, $amount);
        $segments = [
            'services' => max(0.0, (float) $invoice->rate),
            'fines' => max(0.0, (float) $invoice->fines),
            'late_fees' => max(0.0, (float) $invoice->late_fee),
        ];
        $parts = $this->emptyTotals();
        $segmentStart = 0.0;

        foreach ($segments as $concept => $segmentAmount) {
            $segmentEnd = $segmentStart + $segmentAmount;
            $parts[$concept] = max(
                0.0,
                min($allocationEnd, $segmentEnd) - max($allocationStart, $segmentStart),
            );
            $segmentStart = $segmentEnd;
        }

        $unclassified = max(0.0, $amount - array_sum($parts));
        $parts['services'] += $unclassified;

        return $this->rounded($parts);
    }

    /** @return array{services: float, fines: float, late_fees: float} */
    private function emptyTotals(): array
    {
        return ['services' => 0.0, 'fines' => 0.0, 'late_fees' => 0.0];
    }

    /**
     * @param  array{services: float, fines: float, late_fees: float}  $totals
     * @param  array{services: float, fines: float, late_fees: float}  $parts
     */
    private function add(array &$totals, array $parts): void
    {
        foreach (array_keys($totals) as $concept) {
            $totals[$concept] += $parts[$concept];
        }
    }

    /**
     * @param  array{services: float, fines: float, late_fees: float}  $totals
     * @return array{services: float, fines: float, late_fees: float}
     */
    private function rounded(array $totals): array
    {
        return array_map(static fn (float $amount): float => round($amount, 2), $totals);
    }
}
