<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_can_be_filtered_by_customer_name_and_payment_date(): void
    {
        $user = $this->administrator();
        $customerStatus = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $customer = Customer::query()->create([
            'first_name' => 'María',
            'last_name' => 'Quispe',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);
        $otherCustomer = Customer::query()->create([
            'first_name' => 'Luis',
            'last_name' => 'Ramos',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);
        $method = PaymentMethod::query()->create(['name' => 'Efectivo']);
        $old = $this->payment($customer, $method, 'RC26-900001', 'OP26-900001', '2026-08-31 10:00:00');
        $recent = $this->payment($customer, $method, 'RC26-900002', 'OP26-900002', '2026-09-20 10:00:00');
        $other = $this->payment($otherCustomer, $method, 'RC26-900003', 'OP26-900003', '2026-09-20 10:00:00');

        $response = $this->actingAs($user)->get(route('resources.index', [
            'resource' => 'payments',
            'q' => 'María Quispe',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertOk();
        $response->assertSee($recent->receipt_code);
        $response->assertDontSee($old->receipt_code);
        $response->assertDontSee($other->receipt_code);
    }

    private function administrator(): User
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);

        return User::query()->create([
            'name' => 'Admin',
            'last_name' => 'Prueba',
            'email' => 'admin-filter@example.test',
            'password' => 'secret-password',
            'role_id' => $role->id,
            'active' => true,
        ]);
    }

    private function payment(Customer $customer, PaymentMethod $method, string $receiptCode, string $operationNumber, string $paidAt): Payment
    {
        return Payment::query()->create([
            'receipt_code' => $receiptCode,
            'customer_id' => $customer->id,
            'paid_at' => $paidAt,
            'amount' => 15,
            'payment_method_id' => $method->id,
            'source' => 'COUNTER',
            'operation_number' => $operationNumber,
        ]);
    }
}
