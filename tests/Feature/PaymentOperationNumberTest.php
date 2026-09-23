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
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Services\PaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentOperationNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_collected_payments_receive_unique_automatic_operation_numbers(): void
    {
        [$customer, $connection, $period, $method] = $this->paymentContext();
        $firstInvoice = $this->invoice($connection, $period, '2026-09-01', '2026-09-30');
        $secondInvoice = $this->invoice($connection, $period, '2026-10-01', '2026-10-31');
        $service = app(PaymentCollectionService::class);

        $firstPayment = $service->collect($customer, [$firstInvoice->id], [], ['payment_method_id' => $method->id], null);
        $secondPayment = $service->collect($customer, [$secondInvoice->id], [], ['payment_method_id' => $method->id], null);

        $this->assertMatchesRegularExpression('/^OP\d{2}-\d{6}$/', $firstPayment->operation_number);
        $this->assertMatchesRegularExpression('/^OP\d{2}-\d{6}$/', $secondPayment->operation_number);
        $this->assertNotSame($firstPayment->operation_number, $secondPayment->operation_number);
        $this->assertDatabaseHas('payments', ['operation_number' => $firstPayment->operation_number]);
        $this->assertDatabaseHas('payments', ['operation_number' => $secondPayment->operation_number]);
    }

    /** @return array{Customer, Connection, BillingPeriod, PaymentMethod} */
    private function paymentContext(): array
    {
        $customerStatus = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $neighborhood = Neighborhood::query()->create(['name' => 'Sector de prueba', 'active' => true]);
        $customer = Customer::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => '2026-01-01',
        ]);
        $property = Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => 'Dirección de prueba',
            'active' => true,
        ]);
        $connection = Connection::query()->create([
            'property_id' => $property->id,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'Agua'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVE'])->id,
        ]);

        return [
            $customer,
            $connection,
            BillingPeriod::query()->create(['months' => 1, 'description' => 'Mensual']),
            PaymentMethod::query()->create(['name' => 'Efectivo']),
        ];
    }

    private function invoice(Connection $connection, BillingPeriod $period, string $startsOn, string $endsOn): Invoice
    {
        return Invoice::query()->create([
            'connection_id' => $connection->id,
            'billing_period_id' => $period->id,
            'issued_on' => $startsOn,
            'due_on' => '2030-01-31',
            'period_starts_on' => $startsOn,
            'period_ends_on' => $endsOn,
            'rate' => 15,
            'late_fee' => 0,
            'fines' => 0,
            'total' => 15,
            'status' => 'PENDING',
        ]);
    }
}
