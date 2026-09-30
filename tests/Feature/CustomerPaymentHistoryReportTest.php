<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\JassPageController;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_the_exact_customer_and_filters_payment_dates(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-history@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $method = PaymentMethod::query()->create(['name' => 'EFECTIVO']);
        $customerStatus = CustomerStatus::query()->create(['name' => 'ACTIVO']);
        $customer = Customer::query()->create([
            'first_name' => 'Ana María',
            'last_name' => 'Quispe Flores',
            'national_id' => '12345678',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);
        $otherCustomer = Customer::query()->create([
            'first_name' => 'Ana María',
            'last_name' => 'Quispe Flores',
            'national_id' => '87654321',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);

        $this->payment($customer, $method, $administrator, '2026-02-10 09:00:00', 20, Payment::STATUS_ACTIVE);
        $this->payment($customer, $method, $administrator, '2026-02-20 10:00:00', 15, Payment::STATUS_VOIDED);
        $this->payment($customer, $method, $administrator, '2026-03-05 11:00:00', 30, Payment::STATUS_ACTIVE);
        $this->payment($customer, $method, $administrator, '2025-12-20 11:00:00', 5, Payment::STATUS_ACTIVE);
        $this->payment($otherCustomer, $method, $administrator, '2026-02-15 12:00:00', 99, Payment::STATUS_ACTIVE);

        $february = app(ReportService::class)->build('customer-payment-history', [
            'customer_id' => $customer->id,
            'period_type' => 'range',
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ]);

        $this->assertCount(2, $february['rows']);
        $this->assertSame(1, $february['summary']['Pagos válidos']);
        $this->assertSame(1, $february['summary']['Pagos anulados']);
        $this->assertSame(20.0, $february['summary']['Total válido']);
        $this->assertSame('Válido', $february['rows'][0][7]);
        $this->assertSame('Anulado', $february['rows'][1][7]);

        $yearHistory = app(ReportService::class)->build('customer-payment-history', [
            'customer_id' => $customer->id,
            'period_type' => 'year',
            'year' => 2026,
        ]);
        $this->assertCount(3, $yearHistory['rows']);
        $this->assertSame(50.0, $yearHistory['summary']['Total válido']);
        $this->assertStringContainsString('Año 2026', $yearHistory['subtitle']);

        $completeHistory = app(ReportService::class)->build('customer-payment-history', [
            'customer_id' => $customer->id,
            'period_type' => 'all',
        ]);
        $this->assertCount(4, $completeHistory['rows']);
        $this->assertSame(55.0, $completeHistory['summary']['Total válido']);
        $this->assertStringContainsString('12345678', $completeHistory['subtitle']);
        $this->assertStringContainsString('Todo el tiempo', $completeHistory['subtitle']);
        $this->assertStringNotContainsString('87654321', $completeHistory['subtitle']);

        $reportGroup = collect(JassPageController::navigation())->firstWhere('title', 'Reportes');
        $this->assertCount(1, $reportGroup['items']);
        $this->assertSame('reports.index', $reportGroup['items'][0]['route_name']);

        $this->actingAs($administrator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Centro de reportes')
            ->assertSee('DNI 12345678')
            ->assertSee('DNI 87654321')
            ->assertSee('data-customer-report-selector', false)
            ->assertSee('DNI, código, nombres o apellidos')
            ->assertSee('Todo el tiempo')
            ->assertSee('Año completo')
            ->assertDontSee('size="6"', false);

        $this->actingAs($administrator)
            ->get(route('reports.download', [
                'report' => 'customer-payment-history',
                'format' => 'xlsx',
                'customer_id' => $customer->id,
                'period_type' => 'range',
                'from' => '2026-02-01',
                'to' => '2026-02-28',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($administrator)
            ->get(route('reports.download', [
                'report' => 'customer-payment-history',
                'format' => 'pdf',
                'customer_id' => $customer->id,
                'period_type' => 'year',
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function payment(Customer $customer, PaymentMethod $method, User $user, string $paidAt, float $amount, string $status): Payment
    {
        return Payment::query()->create([
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'user_id' => $user->id,
            'paid_at' => $paidAt,
            'amount' => $amount,
            'source' => 'COUNTER',
            'operation_number' => 'OP-'.str_replace(['-', ' ', ':'], '', $paidAt).'-'.$customer->id,
            'status' => $status,
        ]);
    }
}
