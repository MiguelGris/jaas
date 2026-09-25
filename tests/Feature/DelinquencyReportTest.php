<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DebtService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelinquencyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quarterly_charge_becomes_delinquent_only_after_aprils_grace_period(): void
    {
        [$customer] = $this->billingContext();
        $debts = app(DebtService::class);
        $billing = app(BillingService::class);

        $this->assertSame('2026-04-30', $billing->graceDeadline('2026-01-01')->toDateString());
        Invoice::query()->firstOrFail()->forceFill(['due_on' => '2026-05-31'])->saveQuietly();
        $this->assertSame(1, $billing->recalculateDeadlines());
        $this->assertSame('2026-04-30', Invoice::query()->firstOrFail()->due_on->toDateString());

        foreach (['2026-02-28', '2026-03-31', '2026-04-30'] as $date) {
            $this->assertCount(0, $debts->debtors($date), "No debe existir morosidad al {$date}.");
        }

        $mayDebtors = $debts->debtors('2026-05-01');

        $this->assertCount(1, $mayDebtors);
        $this->assertSame($customer->id, $mayDebtors->first()['customer']->id);
        $this->assertCount(1, $mayDebtors->first()['invoices']);
    }

    public function test_a_pending_fine_counts_as_delinquency_even_while_the_charge_is_current(): void
    {
        [$customer] = $this->billingContext();
        $assemblyType = AssemblyType::query()->create(['name' => 'MEETING']);
        $assembly = Assembly::query()->create([
            'assembly_type_id' => $assemblyType->id,
            'held_on' => '2026-03-15',
            'absence_fine' => 0,
            'status' => 'SCHEDULED',
        ]);
        Fine::query()->create([
            'customer_id' => $customer->id,
            'assembly_id' => $assembly->id,
            'reason' => 'Inasistencia',
            'amount' => 8,
            'generated_on' => '2026-03-16',
            'status' => 'PENDING',
        ]);

        $debtor = app(DebtService::class)->debtors('2026-04-01')->first();

        $this->assertNotNull($debtor);
        $this->assertCount(0, $debtor['invoices']);
        $this->assertCount(1, $debtor['fines']);
        $this->assertSame(8.0, $debtor['total']);
    }

    public function test_recommended_reports_can_be_generated_with_operational_data(): void
    {
        [$customer, $connection] = $this->billingContext();
        $method = PaymentMethod::query()->create(['name' => 'CASH']);
        Payment::query()->create([
            'customer_id' => $customer->id,
            'paid_at' => '2026-05-10 10:00:00',
            'amount' => 15,
            'payment_method_id' => $method->id,
            'source' => 'COUNTER',
        ]);
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));
        $reports = app(ReportService::class);

        $aging = $reports->build('debt-aging');
        $paymentMethods = $reports->build('payment-methods', ['month' => '2026-05']);
        $register = $reports->build('service-register');

        $this->assertSame('Antigüedad de la deuda morosa', $aging['title']);
        $this->assertCount(1, $aging['rows']);
        $this->assertSame('Recaudación por medio de pago', $paymentMethods['title']);
        $this->assertSame(['Efectivo', 1, 15.0, '100 %'], $paymentMethods['rows'][0]);
        $this->assertSame('Padrón de conexiones', $register['title']);
        $this->assertSame($connection->supply_code, $register['rows'][0][0]);
    }

    public function test_recommended_reports_are_available_as_pdf_and_excel(): void
    {
        $this->billingContext();
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-reports@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('reports.download', ['report' => 'debt-aging', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($administrator)
            ->get(route('reports.download', ['report' => 'service-register', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_pending_charges_page_does_not_include_paid_charges(): void
    {
        [, $connection] = $this->billingContext();
        $pending = Invoice::query()->firstOrFail();
        $paid = Invoice::query()->create([
            'connection_id' => $connection->id,
            'billing_period_id' => $pending->billing_period_id,
            'issued_on' => '2025-12-28',
            'due_on' => '2026-03-31',
            'period_starts_on' => '2025-12-01',
            'period_ends_on' => '2025-12-31',
            'rate' => 15,
            'late_fee' => 0,
            'fines' => 0,
            'total' => 15,
            'status' => 'PAID',
        ]);

        $response = $this->actingAs($this->administrator())
            ->get(route('resources.index', ['resource' => 'invoices', 'state' => 'pending']));

        $response->assertOk()
            ->assertSee('Cuotas pendientes')
            ->assertSee($pending->invoice_code)
            ->assertDontSee($paid->invoice_code);
    }

    public function test_delinquency_page_only_shows_customer_after_the_grace_deadline(): void
    {
        $this->billingContext();
        $administrator = $this->administrator();

        $this->travelTo(Carbon::parse('2026-04-30 12:00:00'));
        $this->actingAs($administrator)
            ->get(route('delinquencies.index'))
            ->assertOk()
            ->assertDontSee('Ana Prueba');

        $this->travelTo(Carbon::parse('2026-05-01 12:00:00'));
        $this->actingAs($administrator)
            ->get(route('delinquencies.index'))
            ->assertOk()
            ->assertSee('Ana Prueba')
            ->assertSee('Cuotas vencidas');
    }

    public function test_dashboard_links_to_the_filtered_pending_list_and_delinquency_page(): void
    {
        $this->billingContext();

        $this->actingAs($this->administrator())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('resources.index', ['resource' => 'invoices', 'state' => 'pending']), false)
            ->assertSee(route('delinquencies.index'), false);
    }

    private function administrator(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'ADMINISTRATOR']);

        return User::query()->create([
            'name' => 'Administrador de pruebas',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
    }

    /** @return array{Customer, Connection} */
    private function billingContext(): array
    {
        $customerStatus = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $customer = Customer::query()->create([
            'national_id' => '12345678',
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);
        $property = Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => Neighborhood::query()->create(['name' => 'Centro', 'active' => true])->id,
            'address' => 'Dirección de prueba',
            'active' => true,
        ]);
        $connection = Connection::query()->create([
            'property_id' => $property->id,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'WATER'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVE'])->id,
        ]);
        $period = BillingPeriod::query()->create(['months' => 3, 'description' => 'QUARTERLY']);
        Invoice::query()->create([
            'connection_id' => $connection->id,
            'billing_period_id' => $period->id,
            'issued_on' => '2026-01-28',
            'due_on' => '2026-04-30',
            'period_starts_on' => '2026-01-01',
            'period_ends_on' => '2026-01-31',
            'rate' => 15,
            'late_fee' => 0,
            'fines' => 0,
            'total' => 15,
            'status' => 'PENDING',
        ]);

        return [$customer, $connection];
    }
}
