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
     * Build the human-readable concepts that compose one receipt.
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
            $parts = $this->splitInvoiceAmount(
                $invoice,
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
                    'detail' => 'Mora de la cuota '.$period,
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
        $parts = $this->splitInvoiceAmount($allocation->invoice, $amount, $offset);
        $invoiceOffsets[$invoiceId] = round($offset + $amount, 2);

        return $parts;
    }

    private function previousInvoiceAllocation(PaymentAllocation $allocation, Payment $payment): float
    {
        if ($allocation->invoice_id === null) {
            return 0.0;
        }

        return round((float) PaymentAllocation::query()
            ->where('invoice_id', $allocation->invoice_id)
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
