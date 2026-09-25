<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\CashClosing;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeType;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\CashService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_income_and_expense_are_registered_by_the_authenticated_user(): void
    {
        $administrator = $this->administrator();
        $otherUser = User::query()->create([
            'name' => 'Otro',
            'email' => 'otro-caja@example.test',
            'password' => 'Password123',
            'role_id' => $administrator->role_id,
            'active' => true,
        ]);
        $incomeType = IncomeType::query()->create(['name' => 'Donación']);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Mantenimiento']);

        $this->actingAs($administrator)
            ->get(route('resources.create', ['resource' => 'incomes']))
            ->assertOk()
            ->assertDontSee('name="user_id"', false);

        $this->actingAs($administrator)->post(route('resources.store', ['resource' => 'incomes']), [
            'income_type_id' => $incomeType->id,
            'received_on' => '2026-08-10',
            'concept' => 'Ingreso automático',
            'amount' => 75,
            'user_id' => $otherUser->id,
        ])->assertRedirect(route('resources.index', ['resource' => 'incomes']));

        $this->actingAs($administrator)->post(route('resources.store', ['resource' => 'expenses']), [
            'expense_category_id' => $expenseCategory->id,
            'incurred_on' => '2026-08-11',
            'concept' => 'Egreso automático',
            'amount' => 25,
            'user_id' => $otherUser->id,
        ])->assertRedirect(route('resources.index', ['resource' => 'expenses']));

        $this->assertSame($administrator->id, Income::query()->where('concept', 'Ingreso automático')->value('user_id'));
        $this->assertSame($administrator->id, Expense::query()->where('concept', 'Egreso automático')->value('user_id'));

        $income = Income::query()->where('concept', 'Ingreso automático')->firstOrFail();
        $expense = Expense::query()->where('concept', 'Egreso automático')->firstOrFail();
        $this->actingAs($administrator)
            ->get(route('resources.index', ['resource' => 'incomes']))
            ->assertOk()
            ->assertSee('Eliminar');
        $this->actingAs($administrator)
            ->delete(route('resources.destroy', ['resource' => 'incomes', 'record' => $income->id]))
            ->assertRedirect(route('resources.index', ['resource' => 'incomes']));
        $this->actingAs($administrator)
            ->delete(route('resources.destroy', ['resource' => 'expenses', 'record' => $expense->id]))
            ->assertRedirect(route('resources.index', ['resource' => 'expenses']));
        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_cash_closing_is_calculated_from_the_previous_closing_and_dashboard_uses_its_balance(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $administrator = $this->administrator();
        $incomeType = IncomeType::query()->create(['name' => 'Otros ingresos']);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Operación']);
        $paymentMethod = PaymentMethod::query()->create(['name' => 'Efectivo']);

        CashClosing::query()->create([
            'year' => 2026,
            'month' => 7,
            'total_income' => 100,
            'total_expense' => 0,
            'balance' => 100,
            'user_id' => $administrator->id,
            'closed_at' => '2026-08-01 08:00:00',
        ]);
        Payment::query()->create([
            'receipt_code' => 'RC26-810001',
            'paid_at' => '2026-08-15 10:00:00',
            'amount' => 50,
            'payment_method_id' => $paymentMethod->id,
            'source' => 'COUNTER',
            'user_id' => $administrator->id,
            'operation_number' => 'OP26-810001',
        ]);
        Income::query()->create([
            'income_code' => 'ING26-810001',
            'income_type_id' => $incomeType->id,
            'received_on' => '2026-08-20',
            'concept' => 'Ingreso de agosto',
            'amount' => 25,
            'user_id' => $administrator->id,
        ]);
        Expense::query()->create([
            'expense_code' => 'EGR26-810001',
            'expense_category_id' => $expenseCategory->id,
            'incurred_on' => '2026-08-21',
            'concept' => 'Egreso de agosto',
            'amount' => 30,
            'user_id' => $administrator->id,
        ]);
        Income::query()->create([
            'income_code' => 'ING26-910001',
            'income_type_id' => $incomeType->id,
            'received_on' => '2026-09-05',
            'concept' => 'Ingreso posterior',
            'amount' => 10,
            'user_id' => $administrator->id,
        ]);
        Expense::query()->create([
            'expense_code' => 'EGR26-910001',
            'expense_category_id' => $expenseCategory->id,
            'incurred_on' => '2026-09-06',
            'concept' => 'Egreso posterior',
            'amount' => 3,
            'user_id' => $administrator->id,
        ]);

        $response = $this->actingAs($administrator)->post(route('resources.store', ['resource' => 'cash-closings']), [
            'year' => 2026,
            'month' => 8,
            'total_income' => 9999,
            'total_expense' => 9999,
            'balance' => 9999,
            'user_id' => null,
        ]);

        $closing = CashClosing::query()->where('year', 2026)->where('month', 8)->firstOrFail();
        $response->assertRedirect(route('resources.show', ['resource' => 'cash-closings', 'record' => $closing->id]));
        $this->assertSame(75.0, (float) $closing->total_income);
        $this->assertSame(30.0, (float) $closing->total_expense);
        $this->assertSame(145.0, (float) $closing->balance);
        $this->assertSame($administrator->id, $closing->user_id);
        $this->assertSame(152.0, app(CashService::class)->currentBalance()['balance']);

        $augustExpense = Expense::query()->where('concept', 'Egreso de agosto')->firstOrFail();
        $this->actingAs($administrator)
            ->put(route('resources.update', ['resource' => 'expenses', 'record' => $augustExpense->id]), [
                'expense_category_id' => $expenseCategory->id,
                'incurred_on' => '2026-08-21',
                'concept' => 'Egreso alterado',
                'amount' => 1,
            ])
            ->assertSessionHasErrors('incurred_on');
        $this->assertSame(30.0, (float) $augustExpense->fresh()->amount);

        $dashboard = $this->actingAs($administrator)->get(route('dashboard'));
        $dashboard->assertOk();
        $financialMetrics = collect($dashboard->viewData('financialMetrics'));
        $balanceMetric = $financialMetrics->firstWhere('label', 'Saldo en caja');
        $incomeMetric = $financialMetrics->firstWhere('label', 'Ingresos del mes');
        $this->assertSame(152.0, $balanceMetric['value']);
        $this->assertTrue($balanceMetric['wide']);
        $this->assertSame(10.0, $incomeMetric['value']);
    }

    public function test_cash_closing_year_and_month_reject_decimal_values(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('resources.store', ['resource' => 'cash-closings']), [
                'year' => '2026.5',
                'month' => '8.5',
            ])
            ->assertSessionHasErrors(['year', 'month']);

        $this->actingAs($administrator)
            ->post(route('resources.store', ['resource' => 'cash-closings']), [
                'year' => 2026,
                'month' => 9,
            ])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseCount('cash_closings', 0);
    }

    public function test_dashboard_limits_latest_movements_and_recent_invoices_to_five(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $administrator = $this->administrator();
        $incomeType = IncomeType::query()->create(['name' => 'Ingresos varios']);

        foreach (range(1, 7) as $day) {
            Income::query()->create([
                'income_code' => 'ING26-'.str_pad((string) $day, 6, '0', STR_PAD_LEFT),
                'income_type_id' => $incomeType->id,
                'received_on' => "2026-09-{$day}",
                'concept' => "Movimiento {$day}",
                'amount' => $day,
                'user_id' => $administrator->id,
            ]);
        }

        $connection = $this->connection();
        $billingPeriod = BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral']);
        foreach (range(1, 7) as $month) {
            Invoice::query()->create([
                'invoice_code' => 'FAC26-'.str_pad((string) $month, 6, '0', STR_PAD_LEFT),
                'connection_id' => $connection->id,
                'billing_period_id' => $billingPeriod->id,
                'issued_on' => "2026-0{$month}-28",
                'due_on' => "2026-0{$month}-28",
                'period_starts_on' => "2026-0{$month}-01",
                'period_ends_on' => Carbon::create(2026, $month, 1)->endOfMonth()->toDateString(),
                'rate' => 10,
                'late_fee' => 0,
                'fines' => 0,
                'total' => 10,
                'status' => 'PENDING',
            ]);
        }

        $response = $this->actingAs($administrator)->get(route('dashboard'));

        $response->assertOk();
        $this->assertCount(6, $response->viewData('metrics'));
        $this->assertCount(5, $response->viewData('movements'));
        $this->assertCount(5, $response->viewData('recentInvoices'));
        $this->assertSame('FAC26-000007', $response->viewData('recentInvoices')->first()->invoice_code);
    }

    private function administrator(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'ADMINISTRATOR']);

        return User::query()->create([
            'name' => 'Administrador',
            'last_name' => 'Caja',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
    }

    private function connection(): Connection
    {
        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Caja',
            'customer_status_id' => CustomerStatus::query()->create(['name' => 'ACTIVE'])->id,
            'registered_on' => '2026-01-01',
        ]);
        $property = Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => Neighborhood::query()->create(['name' => 'Centro', 'active' => true])->id,
            'address' => 'Dirección de prueba',
            'active' => true,
        ]);

        return Connection::query()->create([
            'property_id' => $property->id,
            'supply_code' => 'SUM_9001',
            'payment_mode' => Connection::PAYMENT_FIXED,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'AGUA'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVE'])->id,
            'installed_on' => '2026-01-01',
        ]);
    }
}
