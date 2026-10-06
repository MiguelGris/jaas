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
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Fine;
use App\Models\Income;
use App\Models\IncomeType;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PaymentConceptReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_and_reports_separate_payment_concepts(): void
    {
        [$administrator, $payment] = $this->context();

        $receipt = $this->actingAs($administrator)->get(route('resources.show', [
            'resource' => 'payments',
            'record' => $payment->id,
        ]));

        $receipt->assertOk()
            ->assertSee('Conceptos pagados')
            ->assertSee('Cuota de servicio 01/2026')
            ->assertSee('Mora del ciclo')
            ->assertSee('Inasistencia a asamblea');

        $this->get(route('receipts.thermal', ['payment' => $payment->id]))
            ->assertOk()->assertSee('Cuota de servicio 01/2026')->assertSee('Mora del ciclo')
            ->assertSee('Inasistencia a asamblea')->assertSee('25.00')->assertDontSee("window.addEventListener('load'", false);

        $monthly = app(ReportService::class)->build('payment-concepts-monthly', ['month' => '2026-01']);
        $monthlyRows = collect($monthly['rows'])->keyBy(0);

        $this->assertSame('Resumen mensual por concepto', $monthly['title']);
        $this->assertSame(15.0, $monthlyRows['Servicios'][1]);
        $this->assertSame(8.0, $monthlyRows['Multas'][1]);
        $this->assertSame(2.0, $monthlyRows['Moras'][1]);
        $this->assertSame(20.0, $monthlyRows['Otros ingresos'][1]);
        $this->assertSame(5.0, $monthlyRows['Egresos'][1]);
        $this->assertSame(40.0, $monthly['summary']['Saldo del período']);

        $annual = app(ReportService::class)->build('payment-concepts-annual', ['year' => 2026]);

        $this->assertSame('Resumen anual por concepto', $annual['title']);
        $this->assertCount(12, $annual['rows']);
        $this->assertSame(15.0, $annual['rows'][0][1]);
        $this->assertSame(8.0, $annual['rows'][0][2]);
        $this->assertSame(2.0, $annual['rows'][0][3]);
        $this->assertSame(20.0, $annual['rows'][0][4]);
        $this->assertSame(5.0, $annual['rows'][0][5]);
        $this->assertSame(40.0, $annual['rows'][0][6]);
        $this->assertSame(40.0, $annual['summary']['Saldo anual']);

        $this->actingAs($administrator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Conceptos de pago mensuales')
            ->assertSee('Conceptos de pago anuales');

        $this->actingAs($administrator)
            ->get(route('reports.download', [
                'report' => 'payment-concepts-monthly',
                'format' => 'pdf',
                'month' => '2026-01',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($administrator)
            ->get(route('reports.download', [
                'report' => 'payment-concepts-annual',
                'format' => 'xlsx',
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_excel_preserves_numeric_zero_concepts(): void
    {
        [$administrator] = $this->context();
        $response = $this->actingAs($administrator)->get(route('reports.download', ['report' => 'payment-concepts-monthly', 'format' => 'xlsx', 'month' => '2026-02']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'jass-xlsx-');
        try {
            file_put_contents($path, $response->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertNotNull($sheet->getCell('B5')->getValue());
            $this->assertSame(0.0, (float) $sheet->getCell('B5')->getValue());
        } finally {
            unlink($path);
        }
    }

    /** @return array{User, Payment} */
    private function context(): array
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-concepts@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Conceptos',
            'customer_status_id' => CustomerStatus::query()->create(['name' => 'ACTIVO'])->id,
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
            'payment_mode' => Connection::PAYMENT_FIXED,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'AGUA'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVO'])->id,
            'installed_on' => '2026-01-01',
        ]);
        $invoice = Invoice::query()->create([
            'invoice_code' => 'FAC26-880001',
            'connection_id' => $connection->id,
            'billing_period_id' => BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral'])->id,
            'issued_on' => '2026-01-28',
            'due_on' => '2026-01-31',
            'period_starts_on' => '2026-01-01',
            'period_ends_on' => '2026-01-31',
            'rate' => 15,
            'late_fee' => 2,
            'fines' => 0,
            'total' => 17,
            'status' => 'PAID',
        ]);
        $assembly = Assembly::query()->create([
            'assembly_code' => 'ASM26-8801',
            'assembly_type_id' => AssemblyType::query()->create(['name' => 'Asamblea'])->id,
            'held_on' => '2026-01-10',
            'absence_fine' => 8,
            'status' => 'SCHEDULED',
        ]);
        $fine = Fine::query()->create([
            'customer_id' => $customer->id,
            'assembly_id' => $assembly->id,
            'reason' => 'Inasistencia a asamblea',
            'amount' => 8,
            'generated_on' => '2026-01-10',
            'status' => 'PAID',
        ]);
        $payment = Payment::query()->create([
            'receipt_code' => 'RC26-880001',
            'customer_id' => $customer->id,
            'paid_at' => '2026-01-15 10:00:00',
            'amount' => 25,
            'payment_method_id' => PaymentMethod::query()->create(['name' => 'EFECTIVO'])->id,
            'source' => 'COUNTER',
            'user_id' => $administrator->id,
            'operation_number' => 'OP26-880001',
            'status' => Payment::STATUS_ACTIVE,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'charge_type' => 'INVOICE',
            'invoice_id' => $invoice->id,
            'amount' => 17,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'charge_type' => 'FINE',
            'fine_id' => $fine->id,
            'amount' => 8,
        ]);
        Income::query()->create([
            'income_type_id' => IncomeType::query()->create(['name' => 'DONACIÓN'])->id,
            'received_on' => '2026-01-16',
            'concept' => 'Donación comunal',
            'amount' => 20,
            'user_id' => $administrator->id,
        ]);
        Expense::query()->create([
            'expense_category_id' => ExpenseCategory::query()->create(['name' => 'MATERIALES'])->id,
            'incurred_on' => '2026-01-17',
            'concept' => 'Compra de materiales',
            'amount' => 5,
            'user_id' => $administrator->id,
        ]);
        Payment::query()->create([
            'receipt_code' => 'RC26-880002',
            'customer_id' => $customer->id,
            'paid_at' => '2026-01-18 10:00:00',
            'amount' => 100,
            'payment_method_id' => $payment->payment_method_id,
            'source' => 'COUNTER',
            'user_id' => $administrator->id,
            'operation_number' => 'OP26-880002',
            'status' => Payment::STATUS_VOIDED,
            'voided_at' => '2026-01-18 11:00:00',
            'voided_by' => $administrator->id,
            'void_reason' => 'Pago de prueba anulado',
        ]);

        return [$administrator, $payment];
    }
}
