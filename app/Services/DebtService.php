<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\LateFeeSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DebtService
{
    /**
     * Obtiene todo cargo pendiente, aunque todavía esté dentro del periodo
     * permitido de pago. Este resultado alimenta la pantalla de cobranza.
     *
     * @return array{invoices: Collection<int, Invoice>, fines: Collection<int, Fine>, total: float}
     */
    public function pendingForCustomer(Customer $customer, CarbonInterface|string|null $asOf = null): array
    {
        $date = Carbon::parse($asOf ?? now());
        $invoices = Invoice::query()
            ->where('status', 'PENDING')
            ->whereHas('connection.property', fn ($query) => $query->where('customer_id', $customer->getKey()))
            ->with(['connection.property', 'billingPeriod'])
            ->withSum('paymentAllocations', 'amount')
            ->orderBy('period_starts_on')
            ->get()
            ->map(fn (Invoice $invoice): Invoice => $this->decorateInvoice($invoice, $date))
            ->filter(fn (Invoice $invoice): bool => $invoice->balance_due > 0)
            ->values();

        $fines = Fine::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', 'PENDING')
            ->with('assembly')
            ->withSum('paymentAllocations', 'amount')
            ->orderBy('generated_on')
            ->get()
            ->map(fn (Fine $fine): Fine => $this->decorateFine($fine))
            ->filter(fn (Fine $fine): bool => $fine->balance_due > 0)
            ->values();

        return [
            'invoices' => $invoices,
            'fines' => $fines,
            'total' => round($invoices->sum('balance_due') + $fines->sum('balance_due'), 2),
        ];
    }

    /**
     * Devuelve solamente deudas que ya convierten al titular en moroso.
     *
     * Las cuotas permanecen vigentes hasta su fecha de vencimiento; una multa
     * pendiente cuenta como morosidad desde el momento en que se genera.
     *
     * @return array{invoices: Collection<int, Invoice>, fines: Collection<int, Fine>, total: float}
     */
    public function delinquentForCustomer(Customer $customer, CarbonInterface|string|null $asOf = null): array
    {
        $date = Carbon::parse($asOf ?? now());
        $pending = $this->pendingForCustomer($customer, $date);
        $invoices = $pending['invoices']
            ->filter(fn (Invoice $invoice): bool => $this->isInvoiceOverdue($invoice, $date))
            ->values();
        $fines = $pending['fines'];

        return [
            'invoices' => $invoices,
            'fines' => $fines,
            'total' => round($invoices->sum('balance_due') + $fines->sum('balance_due'), 2),
        ];
    }

    /**
     * @return Collection<int, array{customer: Customer, invoices: Collection<int, Invoice>, fines: Collection<int, Fine>, total: float}>
     */
    public function debtors(CarbonInterface|string|null $asOf = null): Collection
    {
        $date = Carbon::parse($asOf ?? now())->startOfDay();

        return Customer::query()
            ->where(function ($query) use ($date): void {
                $query->whereHas('properties.connections.invoices', fn ($invoiceQuery) => $invoiceQuery
                    ->where('status', 'PENDING')
                    ->whereDate('due_on', '<', $date))
                    ->orWhereHas('fines', fn ($fineQuery) => $fineQuery->where('status', 'PENDING'));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Customer $customer) use ($date): array {
                $charges = $this->delinquentForCustomer($customer, $date);

                return ['customer' => $customer, ...$charges];
            })
            ->filter(fn (array $debtor): bool => $debtor['total'] > 0)
            ->values();
    }

    public function outstandingTotal(CarbonInterface|string|null $asOf = null): float
    {
        return round($this->debtors($asOf)->sum('total'), 2);
    }

    public function isInvoiceOverdue(Invoice $invoice, CarbonInterface|string|null $asOf = null): bool
    {
        if ($invoice->due_on === null) {
            return false;
        }

        $date = Carbon::parse($asOf ?? now())->startOfDay();

        return $invoice->due_on->copy()->startOfDay()->lt($date);
    }

    public function decorateInvoice(Invoice $invoice, CarbonInterface|string|null $asOf = null): Invoice
    {
        // Estos atributos son calculados para mostrar y cobrar la deuda sin
        // persistirlos hasta que se confirme o anule un pago.
        $date = Carbon::parse($asOf ?? now());
        $calculatedLateFee = $this->lateFeeFor($invoice, $date);
        $lateFee = max((float) $invoice->late_fee, $calculatedLateFee);
        $serviceAmount = round((float) $invoice->rate + (float) $invoice->fines, 2);
        $totalDue = round($serviceAmount + $lateFee, 2);
        $paid = round((float) ($invoice->payment_allocations_sum_amount ?? $invoice->paymentAllocations()->sum('amount')), 2);

        $invoice->setAttribute('service_amount', $serviceAmount);
        $invoice->setAttribute('calculated_late_fee', $lateFee);
        $invoice->setAttribute('amount_due', $totalDue);
        $invoice->setAttribute('paid_amount', $paid);
        $invoice->setAttribute('balance_due', max(0, round($totalDue - $paid, 2)));
        $invoice->setAttribute('late_fee_months', $this->lateFeeMonths($invoice, $date));

        return $invoice;
    }

    public function decorateFine(Fine $fine): Fine
    {
        $paid = round((float) ($fine->payment_allocations_sum_amount ?? $fine->paymentAllocations()->sum('amount')), 2);
        $fine->setAttribute('paid_amount', $paid);
        $fine->setAttribute('balance_due', max(0, round((float) $fine->amount - $paid, 2)));

        return $fine;
    }

    public function synchroniseInvoice(Invoice $invoice, CarbonInterface|string|null $asOf = null): Invoice
    {
        // Tras un cobro o anulación se recalculan total, mora y estado usando
        // solamente asignaciones pertenecientes a pagos activos.
        $invoice = $this->decorateInvoice($invoice, $asOf);
        $lateFee = $invoice->calculated_late_fee;
        $total = $invoice->amount_due;
        $status = $invoice->balance_due <= 0 ? 'PAID' : 'PENDING';
        $this->forgetComputedAttributes($invoice, [
            'service_amount', 'calculated_late_fee', 'amount_due', 'paid_amount',
            'balance_due', 'late_fee_months', 'payment_allocations_sum_amount',
        ]);
        $invoice->late_fee = $lateFee;
        $invoice->total = $total;
        $invoice->status = $status;
        $invoice->save();

        return $invoice;
    }

    public function synchroniseFine(Fine $fine): Fine
    {
        $fine = $this->decorateFine($fine);
        $status = $fine->balance_due <= 0 ? 'PAID' : 'PENDING';
        $this->forgetComputedAttributes($fine, ['paid_amount', 'balance_due', 'payment_allocations_sum_amount']);
        $fine->status = $status;
        $fine->save();

        return $fine;
    }

    private function lateFeeFor(Invoice $invoice, Carbon $date): float
    {
        $setting = $this->lateFeeSetting($date);

        if ($setting === null) {
            return 0;
        }

        return round($this->lateFeeMonths($invoice, $date) * (float) $setting->monthly_amount, 2);
    }

    private function lateFeeMonths(Invoice $invoice, Carbon $date): int
    {
        if ($invoice->due_on === null || ! $date->greaterThan($invoice->due_on)) {
            return 0;
        }

        // El primer mes de mora es el mes calendario posterior al vencimiento.
        return $invoice->due_on->copy()->startOfMonth()->diffInMonths($date->copy()->startOfMonth());
    }

    private function lateFeeSetting(Carbon $date): ?LateFeeSetting
    {
        return LateFeeSetting::query()
            ->whereDate('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->orderByDesc('starts_on')
            ->first();
    }

    /** @param list<string> $attributes */
    private function forgetComputedAttributes(Invoice|Fine $model, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $model->offsetUnset($attribute);
        }
    }
}
