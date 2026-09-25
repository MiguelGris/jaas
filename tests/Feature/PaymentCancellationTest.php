<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\CashService;
use App\Services\DebtService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_payment_can_be_voided_without_deleting_its_receipt(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        [$administrator, $customer, $invoice] = $this->context();
        $method = PaymentMethod::query()->create(['name' => 'EFECTIVO']);
        $payment = Payment::query()->create([
            'receipt_code' => 'RC26-990001',
            'customer_id' => $customer->id,
            'paid_at' => now(),
            'amount' => 20,
            'payment_method_id' => $method->id,
            'source' => 'COUNTER',
            'user_id' => $administrator->id,
            'operation_number' => 'OP26-990001',
            'status' => Payment::STATUS_ACTIVE,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'charge_type' => 'INVOICE',
            'invoice_id' => $invoice->id,
            'amount' => 20,
        ]);

        $this->actingAs($administrator)
            ->get(route('receipts.annul-form', ['payment' => $payment->id]))
            ->assertOk()
            ->assertSee('Anular RC26-990001');

        $response = $this->actingAs($administrator)->patch(route('receipts.annul', ['payment' => $payment->id]), [
            'void_reason' => 'Se seleccionó al cliente equivocado.',
        ]);

        $response->assertRedirect(route('resources.show', ['resource' => 'payments', 'record' => $payment->id]));
        $payment->refresh();
        $this->assertSame(Payment::STATUS_VOIDED, $payment->status);
        $this->assertSame($administrator->id, $payment->voided_by);
        $this->assertSame('Se seleccionó al cliente equivocado.', $payment->void_reason);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('PENDING', $invoice->fresh()->status);
        $this->assertSame(0.0, app(CashService::class)->currentBalance()['balance']);

        $debts = app(DebtService::class)->pendingForCustomer($customer);
        $this->assertCount(1, $debts['invoices']);
        $this->assertSame(20.0, $debts['total']);
        $this->assertSame(0.0, app(ReportService::class)->build('annual-balance', ['year' => 2026])['summary']['Total cobros']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_VOIDED]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'table_name' => 'payments',
            'record_id' => $payment->id,
            'action' => 'UPDATE',
        ]);
        $this->actingAs($administrator)
            ->get(route('receipts.thermal', ['payment' => $payment->id]))
            ->assertOk()
            ->assertSee('RECIBO ANULADO')
            ->assertSee('Se seleccionó al cliente equivocado.')
            ->assertSee('@page { size: 58mm auto; margin: 0; }', false)
            ->assertSee('papel de 58 mm');
    }

    /** @return array{User, Customer, Invoice} */
    private function context(): array
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-annul@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Anulación',
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
            'supply_code' => 'SUM_9901',
            'payment_mode' => Connection::PAYMENT_FIXED,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'AGUA'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVO'])->id,
            'installed_on' => '2026-01-01',
        ]);
        $invoice = Invoice::query()->create([
            'invoice_code' => 'FAC26-990001',
            'connection_id' => $connection->id,
            'billing_period_id' => BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral'])->id,
            'issued_on' => '2026-09-01',
            'due_on' => '2026-09-30',
            'period_starts_on' => '2026-09-01',
            'period_ends_on' => '2026-09-30',
            'rate' => 20,
            'late_fee' => 0,
            'fines' => 0,
            'total' => 20,
            'status' => 'PAID',
        ]);

        return [$administrator, $customer, $invoice];
    }
}
