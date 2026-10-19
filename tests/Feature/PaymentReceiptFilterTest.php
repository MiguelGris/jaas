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

    public function test_shared_lists_filter_before_paginating_and_preserve_the_page_size(): void
    {
        $user = $this->administrator();
        for ($i = 1; $i <= 28; $i++) {
            \App\Models\Neighborhood::query()->create(['name' => 'Sector '.$i, 'active' => true]);
        }
        \App\Models\Neighborhood::query()->create(['name' => 'Otro barrio', 'active' => true]);
        $response = $this->actingAs($user)->get(route('resources.index', ['resource' => 'neighborhoods', 'q' => 'Sector', 'per_page' => 25]));
        $response->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 28 && $records->count() === 25 && $records->perPage() === 25);
        $response->assertDontSee('Otro barrio')->assertSee('per_page=25', false);
        $this->get(route('resources.index', ['resource' => 'neighborhoods', 'q' => 'Sector', 'per_page' => 25, 'page' => 2]))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 3);
        foreach ([25, 50, 100, 200] as $size) {
            $this->get(route('resources.index', ['resource' => 'neighborhoods', 'per_page' => $size]))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->perPage() === $size);
        }
        $this->get(route('resources.index', ['resource' => 'neighborhoods']))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->perPage() === 25);
    }

    public function test_receipts_can_be_searched_by_receipt_and_operation_number(): void
    {
        $user = $this->administrator();
        $status = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Perez', 'customer_status_id' => $status->id, 'registered_on' => '2026-01-01']);
        $method = PaymentMethod::query()->create(['name' => 'Efectivo']);
        $payment = $this->payment($customer, $method, 'RC26-900001', 'OP26-900001', '2026-09-20 10:00:00');
        foreach ([$payment->receipt_code, $payment->operation_number] as $term) {
            $this->actingAs($user)->get(route('resources.index', ['resource' => 'payments', 'q' => $term]))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
        }
    }
    public function test_all_shared_lists_accept_search_and_page_size(): void
    {
        $user = $this->administrator();
        foreach (\App\Http\Controllers\Web\JassPageController::definitions() as $resource => $definition) {
            if (in_array($resource, ['settings', 'late-fee-settings', 'permissions'])) {
                continue;
            }
            $this->actingAs($user)->get(route('resources.index', ['resource' => $resource, 'q' => 'inexistente', 'per_page' => 100]))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->perPage() === 100);
        }
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
