<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ReportService
{
    public function __construct(private readonly DebtService $debts)
    {
    }

    /**
     * @param array{month?: string|null, assembly_id?: int|string|null} $filters
     * @return array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>}
     */
    public function build(string $report, array $filters = []): array
    {
        return match ($report) {
            'cash-flow' => $this->cashFlow($filters['month'] ?? null),
            'debtors' => $this->debtors(),
            'attendance' => $this->attendance($filters['assembly_id'] ?? null),
            'work-exemptions' => $this->workExemptions(),
            default => abort(404),
        };
    }

    private function cashFlow(?string $month): array
    {
        $period = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $entries = collect();

        Payment::query()->with('customer')->whereBetween('paid_at', [$start, $end])->get()->each(function (Payment $payment) use ($entries): void {
            $customer = $payment->customer;
            $entries->push([
                'date' => $payment->paid_at->toDateString(), 'type' => 'Cobro de cuota', 'code' => $payment->receipt_code,
                'concept' => trim(($customer?->last_name ?? '').', '.($customer?->first_name ?? '')),
                'income' => (float) $payment->amount, 'expense' => 0.0,
            ]);
        });
        Income::query()->with('incomeType')->whereBetween('received_on', [$start, $end])->get()->each(function (Income $income) use ($entries): void {
            $entries->push([
                'date' => $income->received_on->toDateString(), 'type' => $income->incomeType?->name ?? 'Ingreso', 'code' => $income->income_code,
                'concept' => $income->concept ?? 'Ingreso registrado', 'income' => (float) $income->amount, 'expense' => 0.0,
            ]);
        });
        Expense::query()->with('expenseCategory')->whereBetween('incurred_on', [$start, $end])->get()->each(function (Expense $expense) use ($entries): void {
            $entries->push([
                'date' => $expense->incurred_on->toDateString(), 'type' => $expense->expenseCategory?->name ?? 'Egreso', 'code' => $expense->expense_code,
                'concept' => $expense->concept ?? 'Egreso registrado', 'income' => 0.0, 'expense' => (float) $expense->amount,
            ]);
        });

        $balance = 0.0;
        $rows = $entries->sortBy('date')->map(function (array $entry) use (&$balance): array {
            $balance = round($balance + $entry['income'] - $entry['expense'], 2);

            return [Carbon::parse($entry['date'])->format('d/m/Y'), $entry['type'], $entry['code'], $entry['concept'], $entry['income'], $entry['expense'], $balance];
        })->values();
        $totalIncome = round($entries->sum('income'), 2);
        $totalExpense = round($entries->sum('expense'), 2);

        return $this->document(
            'Flujo de caja mensual', $period->translatedFormat('F Y'), 'flujo-caja-'.$period->format('Y-m'),
            ['Fecha', 'Tipo', 'Código', 'Concepto', 'Ingreso', 'Egreso', 'Saldo'], $rows->all(),
            ['Total ingresos' => $totalIncome, 'Total egresos' => $totalExpense, 'Saldo del período' => round($totalIncome - $totalExpense, 2)], [4, 5, 6],
        );
    }

    private function debtors(): array
    {
        $rows = $this->debts->debtors()->map(function (array $debtor): array {
            /** @var Customer $customer */
            $customer = $debtor['customer'];

            return [
                $customer->customer_code, $customer->national_id ?? '—', trim($customer->last_name.', '.$customer->first_name), $customer->phone ?? '—',
                $debtor['invoices']->count(), $debtor['fines']->count(), (float) $debtor['total'],
            ];
        });

        return $this->document(
            'Lista de morosos', 'Deudas pendientes al '.now()->format('d/m/Y'), 'morosos-'.now()->format('Y-m-d'),
            ['Código', 'DNI', 'Titular', 'Teléfono', 'Cuotas', 'Multas', 'Deuda total'], $rows->all(),
            ['Morosos' => $rows->count(), 'Deuda por cobrar' => round($rows->sum(6), 2)], [6],
        );
    }

    private function attendance(int|string|null $assemblyId): array
    {
        $assembly = Assembly::query()
            ->when($assemblyId, fn ($query) => $query->whereKey($assemblyId))
            ->orderByDesc('held_on')
            ->firstOrFail();
        $attendances = AssemblyAttendance::query()
            ->where('assembly_id', $assembly->getKey())
            ->with('customer')
            ->orderBy('customer_id')
            ->get();
        $rows = $attendances->map(fn (AssemblyAttendance $attendance): array => [
            $attendance->customer?->customer_code ?? '—', $attendance->customer?->national_id ?? '—',
            trim(($attendance->customer?->last_name ?? '').', '.($attendance->customer?->first_name ?? '')),
            $attendance->customer?->phone ?? '—', $attendance->attended ? 'Asistió' : 'Inasistente', $attendance->notes ?? '',
        ]);

        return $this->document(
            'Asistentes e inasistentes', "{$assembly->assembly_code} - {$assembly->held_on->format('d/m/Y')}", 'asistencia-'.$assembly->assembly_code,
            ['Código', 'DNI', 'Titular', 'Teléfono', 'Estado', 'Observaciones'], $rows->all(),
            ['Asistentes' => $attendances->where('attended', true)->count(), 'Inasistentes' => $attendances->where('attended', false)->count()], [],
        );
    }

    private function workExemptions(): array
    {
        $age = $this->workExemptionAge();
        $customers = Customer::query()
            ->with('properties')
            ->whereNotNull('birth_date')
            ->whereHas('properties')
            ->whereHas('customerStatus', fn ($query) => $query->whereIn('name', ['ACTIVE', 'ACTIVO']))
            ->orderBy('last_name')
            ->get()
            ->filter(fn (Customer $customer): bool => $customer->birth_date->age >= $age);
        $rows = $customers->flatMap(function (Customer $customer): Collection {
            return $customer->properties->map(fn ($property): array => [
                $customer->customer_code, $customer->national_id ?? '—', trim($customer->last_name.', '.$customer->first_name),
                $customer->birth_date->format('d/m/Y'), $customer->birth_date->age, $property->property_code ?? '—', $property->address,
            ]);
        });

        return $this->document(
            'Exonerados de faenas por edad', "Titulares activos de {$age} años o más", 'exonerados-faenas-'.now()->format('Y-m-d'),
            ['Código', 'DNI', 'Titular', 'Fecha de nacimiento', 'Edad', 'Predio', 'Dirección'], $rows->all(),
            ['Edad mínima' => $age, 'Titulares exonerados' => $customers->count()], [],
        );
    }

    public function workExemptionAge(): int
    {
        $age = (int) Setting::query()->where('key', 'work_exemption_age')->value('value');

        return $age >= 18 && $age <= 120 ? $age : 65;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|float>> $rows
     * @param array<string, string|int|float> $summary
     * @param list<int> $currencyColumns
     * @return array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>}
     */
    private function document(string $title, string $subtitle, string $filename, array $headers, array $rows, array $summary, array $currencyColumns): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'filename' => $filename,
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
            'currency_columns' => $currencyColumns,
        ];
    }
}
