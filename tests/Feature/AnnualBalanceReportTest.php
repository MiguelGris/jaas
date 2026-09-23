<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeType;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_balance_groups_income_and_expenses_by_month(): void
    {
        $incomeType = IncomeType::query()->create(['name' => 'Donación']);
        Income::query()->create([
            'income_type_id' => $incomeType->id,
            'received_on' => '2026-01-15',
            'concept' => 'Ingreso de prueba',
            'amount' => 100,
        ]);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Mantenimiento']);
        Expense::query()->create([
            'expense_category_id' => $expenseCategory->id,
            'incurred_on' => '2026-01-20',
            'concept' => 'Gasto de prueba',
            'amount' => 40,
        ]);

        $report = app(ReportService::class)->build('annual-balance', ['year' => 2026]);

        $this->assertSame('Balance anual', $report['title']);
        $this->assertCount(12, $report['rows']);
        $this->assertSame(100.0, $report['rows'][0][3]);
        $this->assertSame(40.0, $report['rows'][0][4]);
        $this->assertSame(60.0, $report['rows'][0][5]);
        $this->assertSame(60.0, $report['summary']['Saldo anual']);
    }
}
