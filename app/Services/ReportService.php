<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Connection;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\CatalogLabel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ReportService
{
    public function __construct(
        private readonly DebtService $debts,
        private readonly PaymentConceptService $paymentConcepts,
    ) {}

    /**
     * @param  array{month?: string|null, year?: int|string|null, assembly_id?: int|string|null}  $filters
     * @return array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>}
     */
    public function build(string $report, array $filters = []): array
    {
        return match ($report) {
            'cash-flow' => $this->cashFlow($filters['month'] ?? null),
            'annual-balance' => $this->annualBalance($filters['year'] ?? null),
            'payment-concepts-monthly' => $this->paymentConceptsMonthly($filters['month'] ?? null),
            'payment-concepts-annual' => $this->paymentConceptsAnnual($filters['year'] ?? null),
            'debtors' => $this->debtors(),
            'debt-aging' => $this->debtAging(),
            'payment-methods' => $this->paymentMethods($filters['month'] ?? null),
            'service-register' => $this->serviceRegister(),
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

        Payment::query()->active()->with('customer')->whereBetween('paid_at', [$start, $end])->get()->each(function (Payment $payment) use ($entries): void {
            $customer = $payment->customer;
            $entries->push([
                'date' => $payment->paid_at->toDateString(), 'type' => 'Cobro de cuota', 'code' => $payment->receipt_code,
                'concept' => trim(($customer?->last_name ?? '').', '.($customer?->first_name ?? '')),
                'income' => (float) $payment->amount, 'expense' => 0.0,
            ]);
        });
        Income::query()->with('incomeType')->whereBetween('received_on', [$start, $end])->get()->each(function (Income $income) use ($entries): void {
            $entries->push([
                'date' => $income->received_on->toDateString(), 'type' => CatalogLabel::value($income->incomeType?->name ?? 'Ingreso'), 'code' => $income->income_code,
                'concept' => $income->concept ?? 'Ingreso registrado', 'income' => (float) $income->amount, 'expense' => 0.0,
            ]);
        });
        Expense::query()->with('expenseCategory')->whereBetween('incurred_on', [$start, $end])->get()->each(function (Expense $expense) use ($entries): void {
            $entries->push([
                'date' => $expense->incurred_on->toDateString(), 'type' => CatalogLabel::value($expense->expenseCategory?->name ?? 'Egreso'), 'code' => $expense->expense_code,
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

    private function annualBalance(int|string|null $selectedYear): array
    {
        $year = (int) ($selectedYear ?: now()->year);
        $payments = Payment::query()
            ->active()
            ->whereYear('paid_at', $year)
            ->get()
            ->groupBy(fn (Payment $payment): int => $payment->paid_at->month)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $incomes = Income::query()
            ->whereYear('received_on', $year)
            ->get()
            ->groupBy(fn (Income $income): int => $income->received_on->month)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $expenses = Expense::query()
            ->whereYear('incurred_on', $year)
            ->get()
            ->groupBy(fn (Expense $expense): int => $expense->incurred_on->month)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $accumulated = 0.0;
        $rows = collect(range(1, 12))->map(function (int $month) use ($year, $payments, $incomes, $expenses, &$accumulated): array {
            $collections = (float) $payments->get($month, 0);
            $otherIncome = (float) $incomes->get($month, 0);
            $totalIncome = round($collections + $otherIncome, 2);
            $totalExpense = (float) $expenses->get($month, 0);
            $monthlyBalance = round($totalIncome - $totalExpense, 2);
            $accumulated = round($accumulated + $monthlyBalance, 2);

            return [
                Carbon::create($year, $month, 1)->locale('es')->translatedFormat('F'),
                $collections,
                $otherIncome,
                $totalIncome,
                $totalExpense,
                $monthlyBalance,
                $accumulated,
            ];
        });
        $totalCollections = round((float) $payments->sum(), 2);
        $totalOtherIncome = round((float) $incomes->sum(), 2);
        $totalExpense = round((float) $expenses->sum(), 2);
        $totalIncome = round($totalCollections + $totalOtherIncome, 2);

        return $this->document(
            'Balance anual', "Ejercicio {$year}", "balance-anual-{$year}",
            ['Mes', 'Cobros', 'Otros ingresos', 'Ingresos totales', 'Egresos', 'Saldo mensual', 'Saldo acumulado'], $rows->all(),
            ['Total cobros' => $totalCollections, 'Otros ingresos' => $totalOtherIncome, 'Ingresos totales' => $totalIncome, 'Egresos totales' => $totalExpense, 'Saldo anual' => round($totalIncome - $totalExpense, 2)], [1, 2, 3, 4, 5, 6],
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
            'Lista de morosos', 'Cuotas vencidas y multas pendientes al '.now()->format('d/m/Y'), 'morosos-'.now()->format('Y-m-d'),
            ['Código', 'DNI', 'Titular', 'Teléfono', 'Cuotas vencidas', 'Multas', 'Deuda morosa'], $rows->all(),
            ['Morosos' => $rows->count(), 'Deuda morosa total' => round($rows->sum(6), 2)], [6],
        );
    }

    private function paymentConceptsMonthly(?string $month): array
    {
        $period = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $concepts = $this->paymentConcepts->totals($start, $end);
        $otherIncome = round((float) Income::query()->whereBetween('received_on', [$start, $end])->sum('amount'), 2);
        $expenses = round((float) Expense::query()->whereBetween('incurred_on', [$start, $end])->sum('amount'), 2);
        $collection = round($concepts['services'] + $concepts['fines'] + $concepts['late_fees'], 2);
        $totalIncome = round($collection + $otherIncome, 2);
        $rows = [
            ['Servicios', $concepts['services']],
            ['Multas', $concepts['fines']],
            ['Moras', $concepts['late_fees']],
            ['Otros ingresos', $otherIncome],
            ['Egresos', $expenses],
        ];

        return $this->document(
            'Resumen mensual por concepto', $period->translatedFormat('F Y'), 'conceptos-mensual-'.$period->format('Y-m'),
            ['Concepto', 'Monto'], $rows,
            [
                'Recaudación por pagos' => $collection,
                'Entradas totales' => $totalIncome,
                'Egresos totales' => $expenses,
                'Saldo del período' => round($totalIncome - $expenses, 2),
            ], [1],
        );
    }

    private function paymentConceptsAnnual(int|string|null $selectedYear): array
    {
        $year = (int) ($selectedYear ?: now()->year);
        $concepts = $this->paymentConcepts->totalsByMonth($year);
        $incomes = Income::query()
            ->whereYear('received_on', $year)
            ->get()
            ->groupBy(fn (Income $income): int => $income->received_on->month)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $expenses = Expense::query()
            ->whereYear('incurred_on', $year)
            ->get()
            ->groupBy(fn (Expense $expense): int => $expense->incurred_on->month)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $rows = collect(range(1, 12))->map(function (int $month) use ($year, $concepts, $incomes, $expenses): array {
            $services = $concepts[$month]['services'];
            $fines = $concepts[$month]['fines'];
            $lateFees = $concepts[$month]['late_fees'];
            $otherIncome = (float) $incomes->get($month, 0);
            $expense = (float) $expenses->get($month, 0);

            return [
                Carbon::create($year, $month, 1)->locale('es')->translatedFormat('F'),
                $services,
                $fines,
                $lateFees,
                $otherIncome,
                $expense,
                round($services + $fines + $lateFees + $otherIncome - $expense, 2),
            ];
        });
        $totalServices = round((float) $rows->sum(1), 2);
        $totalFines = round((float) $rows->sum(2), 2);
        $totalLateFees = round((float) $rows->sum(3), 2);
        $totalOtherIncome = round((float) $rows->sum(4), 2);
        $totalExpenses = round((float) $rows->sum(5), 2);

        return $this->document(
            'Resumen anual por concepto', "Ejercicio {$year}", "conceptos-anual-{$year}",
            ['Mes', 'Servicios', 'Multas', 'Moras', 'Otros ingresos', 'Egresos', 'Saldo'], $rows->all(),
            [
                'Servicios' => $totalServices,
                'Multas' => $totalFines,
                'Moras' => $totalLateFees,
                'Otros ingresos' => $totalOtherIncome,
                'Egresos' => $totalExpenses,
                'Saldo anual' => round($totalServices + $totalFines + $totalLateFees + $totalOtherIncome - $totalExpenses, 2),
            ], [1, 2, 3, 4, 5, 6],
        );
    }

    private function debtAging(): array
    {
        $asOf = now()->startOfDay();
        $rows = $this->debts->debtors($asOf)
            ->flatMap(function (array $debtor) use ($asOf): Collection {
                /** @var Customer $customer */
                $customer = $debtor['customer'];
                $identity = [
                    $customer->customer_code,
                    $customer->national_id ?? '—',
                    trim($customer->last_name.', '.$customer->first_name),
                ];
                $invoiceRows = $debtor['invoices']->map(function ($invoice) use ($identity, $asOf): array {
                    $days = (int) $invoice->due_on->copy()->startOfDay()->diffInDays($asOf);

                    return [
                        ...$identity,
                        'Cuota vencida',
                        $invoice->invoice_code,
                        $invoice->due_on->format('d/m/Y'),
                        $days,
                        $this->agingBucket($days),
                        (float) $invoice->balance_due,
                    ];
                });
                $fineRows = $debtor['fines']->map(function ($fine) use ($identity, $asOf): array {
                    $days = $fine->generated_on === null
                        ? 0
                        : max(0, (int) $fine->generated_on->copy()->startOfDay()->diffInDays($asOf));

                    return [
                        ...$identity,
                        'Multa pendiente',
                        $fine->fine_code,
                        $fine->generated_on?->format('d/m/Y') ?? '—',
                        $days,
                        $this->agingBucket($days),
                        (float) $fine->balance_due,
                    ];
                });

                return $invoiceRows->concat($fineRows);
            })
            ->sortByDesc(6)
            ->values();

        $bucketAmount = fn (string $bucket): float => round((float) $rows->where(7, $bucket)->sum(8), 2);

        return $this->document(
            'Antigüedad de la deuda morosa', 'Cuotas vencidas y multas pendientes al '.$asOf->format('d/m/Y'), 'antiguedad-deuda-'.$asOf->format('Y-m-d'),
            ['Código', 'DNI', 'Titular', 'Tipo', 'Documento', 'Fecha de mora', 'Días', 'Antigüedad', 'Saldo'], $rows->all(),
            [
                'Deuda total' => round((float) $rows->sum(8), 2),
                'Deuda de 0 a 30 días' => $bucketAmount('0 a 30 días'),
                'Deuda de 31 a 60 días' => $bucketAmount('31 a 60 días'),
                'Deuda de 61 a 90 días' => $bucketAmount('61 a 90 días'),
                'Deuda de más de 90 días' => $bucketAmount('Más de 90 días'),
            ],
            [8],
        );
    }

    private function paymentMethods(?string $month): array
    {
        $period = Carbon::createFromFormat('Y-m', $month ?: now()->format('Y-m'))->startOfMonth();
        $payments = Payment::query()
            ->active()
            ->with('paymentMethod')
            ->whereBetween('paid_at', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->get();
        $total = round((float) $payments->sum('amount'), 2);
        $rows = $payments
            ->groupBy(fn (Payment $payment): string => CatalogLabel::value($payment->paymentMethod?->name ?? 'Sin especificar'))
            ->map(function (Collection $items, string $method) use ($total): array {
                $amount = round((float) $items->sum('amount'), 2);

                return [$method, $items->count(), $amount, $total > 0 ? round(($amount / $total) * 100, 2).' %' : '0 %'];
            })
            ->sortByDesc(2)
            ->values();

        return $this->document(
            'Recaudación por medio de pago', $period->translatedFormat('F Y'), 'recaudacion-medios-'.$period->format('Y-m'),
            ['Medio de pago', 'Operaciones', 'Recaudado', 'Participación'], $rows->all(),
            ['Pagos registrados' => $payments->count(), 'Cobros totales' => $total], [2],
        );
    }

    private function serviceRegister(): array
    {
        $connections = Connection::query()
            ->with(['property.customer', 'connectionType', 'connectionStatus', 'meters'])
            ->orderBy('supply_code')
            ->get();
        $rows = $connections->map(function (Connection $connection): array {
            $customer = $connection->property?->customer;
            $meter = $connection->meters->firstWhere('active', true);

            return [
                $connection->supply_code,
                $customer?->customer_code ?? '—',
                $customer?->national_id ?? '—',
                $customer ? trim($customer->last_name.', '.$customer->first_name) : '—',
                $connection->property?->property_code ?? '—',
                $connection->property?->address ?? '—',
                CatalogLabel::value($connection->connectionType?->name ?? '—'),
                CatalogLabel::value($connection->payment_mode),
                CatalogLabel::value($connection->connectionStatus?->name ?? '—'),
                $meter?->meter_number ?? '—',
            ];
        });

        return $this->document(
            'Padrón de conexiones', 'Clientes, predios y servicios registrados al '.now()->format('d/m/Y'), 'padron-conexiones-'.now()->format('Y-m-d'),
            ['Suministro', 'Cliente', 'DNI', 'Titular', 'Predio', 'Dirección', 'Servicio', 'Cobro', 'Estado', 'Medidor'], $rows->all(),
            [
                'Conexiones registradas' => $connections->count(),
                'Conexiones con pago fijo' => $connections->where('payment_mode', Connection::PAYMENT_FIXED)->count(),
                'Conexiones con medidor' => $connections->where('payment_mode', Connection::PAYMENT_METERED)->count(),
            ], [],
        );
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 30 => '0 a 30 días',
            $days <= 60 => '31 a 60 días',
            $days <= 90 => '61 a 90 días',
            default => 'Más de 90 días',
        };
    }

    private function attendance(int|string|null $assemblyId): array
    {
        $assembly = Assembly::query()
            ->when($assemblyId, fn ($query) => $query->whereKey($assemblyId))
            ->orderByDesc('held_on')
            ->firstOrFail();
        $attendances = AssemblyAttendance::query()
            ->where('assembly_id', $assembly->getKey())
            ->with('customer.properties.neighborhood')
            ->orderBy('customer_id')
            ->get();
        $attendanceData = $attendances->map(function (AssemblyAttendance $attendance): array {
            $customer = $attendance->customer;
            $property = $customer?->properties->firstWhere('active', true) ?? $customer?->properties->first();

            return [
                'attendance' => $attendance,
                'neighborhood' => $property?->neighborhood?->name ?? 'Sin barrio',
            ];
        });
        $rows = $attendanceData->map(function (array $data): array {
            /** @var AssemblyAttendance $attendance */
            $attendance = $data['attendance'];

            return [
                $attendance->customer?->customer_code ?? '—',
                $attendance->customer?->national_id ?? '—',
                trim(($attendance->customer?->last_name ?? '').', '.($attendance->customer?->first_name ?? '')),
                $data['neighborhood'],
                $attendance->customer?->phone ?? '—',
                $attendance->attended ? 'Asistió' : 'Inasistente',
                $attendance->notes ?? '',
            ];
        });
        $total = $attendances->count();
        $present = $attendances->where('attended', true)->count();
        $absent = $total - $present;
        $percentage = static fn (int $value, int $base): string => $base > 0
            ? number_format(($value / $base) * 100, 2).' %'
            : '0.00 %';
        $neighborhoodRows = $attendanceData
            ->groupBy('neighborhood')
            ->map(function (Collection $items, string $neighborhood) use ($percentage): array {
                $neighborhoodTotal = $items->count();
                $neighborhoodPresent = $items->filter(
                    fn (array $data): bool => $data['attendance']->attended
                )->count();

                return [
                    $neighborhood,
                    $neighborhoodTotal,
                    $neighborhoodPresent,
                    $neighborhoodTotal - $neighborhoodPresent,
                    $percentage($neighborhoodPresent, $neighborhoodTotal),
                ];
            })
            ->sortBy(fn (array $row): string => $row[0] === 'Sin barrio' ? 'zzzz-sin-barrio' : mb_strtolower($row[0]))
            ->values()
            ->all();

        return $this->document(
            'Asistentes e inasistentes', "{$assembly->assembly_code} - {$assembly->held_on->format('d/m/Y')}", 'asistencia-'.$assembly->assembly_code,
            ['Código', 'DNI', 'Titular', 'Barrio', 'Teléfono', 'Estado', 'Observaciones'], $rows->all(),
            [], [],
            [
                [
                    'title' => 'Resumen general',
                    'headers' => ['Convocados', 'Asistentes', 'Inasistentes', 'Porcentaje de asistencia'],
                    'rows' => [[$total, $present, $absent, $percentage($present, $total)]],
                ],
                [
                    'title' => 'Resumen por barrio',
                    'headers' => ['Barrio', 'Convocados', 'Asistentes', 'Inasistentes', 'Porcentaje de asistencia'],
                    'rows' => $neighborhoodRows,
                ],
            ],
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
     * @param  list<string>  $headers
     * @param  list<list<string|int|float>>  $rows
     * @param  array<string, string|int|float>  $summary
     * @param  list<int>  $currencyColumns
     * @param  list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>  $introTables
     * @return array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>, intro_tables: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>}
     */
    private function document(string $title, string $subtitle, string $filename, array $headers, array $rows, array $summary, array $currencyColumns, array $introTables = []): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'filename' => $filename,
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
            'currency_columns' => $currencyColumns,
            'intro_tables' => $introTables,
        ];
    }
}
