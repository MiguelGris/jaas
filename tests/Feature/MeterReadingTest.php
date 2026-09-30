<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\ConnectionUsageType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\Neighborhood;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\UsageType;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeterReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_connections_default_to_fixed_billing_and_meter_readings_calculate_consumption(): void
    {
        [$fixed, $metered] = $this->connections();
        $meter = Meter::query()->create([
            'connection_id' => $metered->id,
            'meter_number' => 'MED-PRUEBA-001',
            'initial_reading' => 100,
            'active' => true,
        ]);
        $service = app(MeterReadingService::class);

        $first = $service->create([
            'meter_id' => $meter->id,
            'read_on' => '2026-09-01',
            'current_reading' => 125,
        ]);
        $second = $service->create([
            'meter_id' => $meter->id,
            'read_on' => '2026-10-01',
            'current_reading' => 145,
        ]);

        $this->assertSame(Connection::PAYMENT_FIXED, $fixed->refresh()->payment_mode);
        $this->assertSame('100.00', $first->previous_reading);
        $this->assertSame('25.00', $first->consumption);
        $this->assertSame('125.00', $second->previous_reading);
        $this->assertSame('20.00', $second->consumption);
    }

    public function test_readings_are_rejected_when_the_connection_uses_fixed_payment(): void
    {
        [$fixed] = $this->connections();
        $meter = Meter::query()->create([
            'connection_id' => $fixed->id,
            'meter_number' => 'MED-FIJO-001',
            'initial_reading' => 0,
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(MeterReadingService::class)->create([
            'meter_id' => $meter->id,
            'read_on' => '2026-09-01',
            'current_reading' => 10,
        ]);
    }

    public function test_the_api_uses_the_meter_reading_service_to_calculate_consumption(): void
    {
        [, $metered] = $this->connections();
        $meter = Meter::query()->create([
            'connection_id' => $metered->id,
            'meter_number' => 'MED-API-001',
            'initial_reading' => 40,
            'active' => true,
        ]);
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-meter-api@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);

        $response = $this->actingAs($administrator)->postJson(route('v1.meter-readings.store'), [
            'meter_id' => $meter->id,
            'read_on' => '2026-09-15',
            'current_reading' => 52.5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $meter->id,
            'previous_reading' => 40,
            'current_reading' => 52.5,
            'consumption' => 12.5,
            'user_id' => $administrator->id,
        ]);
    }

    public function test_monthly_billing_uses_the_metered_consumption_and_unit_price(): void
    {
        [, $metered] = $this->connections();
        $usageType = UsageType::query()->create(['name' => 'RESIDENCIAL']);
        ConnectionUsageType::query()->create([
            'connection_id' => $metered->id,
            'usage_type_id' => $usageType->id,
            'starts_on' => '2026-01-01',
        ]);
        BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral']);
        Rate::query()->create([
            'usage_type_id' => $usageType->id,
            'year' => 2026,
            'amount' => 15,
            'metered_unit_price' => 1.75,
            'starts_on' => '2026-01-01',
        ]);
        $meter = Meter::query()->create([
            'connection_id' => $metered->id,
            'meter_number' => 'MED-FACT-001',
            'initial_reading' => 100,
            'active' => true,
        ]);
        app(MeterReadingService::class)->create([
            'meter_id' => $meter->id,
            'read_on' => '2026-09-28',
            'current_reading' => 112,
        ]);

        $created = app(BillingService::class)->generateForMonth('2026-09');

        $this->assertSame(1, $created);
        $invoice = Invoice::query()->where('connection_id', $metered->id)->firstOrFail();
        $this->assertSame('21.00', $invoice->rate);
        $this->assertSame('21.00', $invoice->total);
    }

    /** @return array{Connection, Connection} */
    private function connections(): array
    {
        $customerStatus = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $neighborhood = Neighborhood::query()->create(['name' => 'Sector de prueba', 'active' => true]);
        $customer = Customer::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'customer_status_id' => $customerStatus->id,
            'registered_on' => now()->toDateString(),
        ]);
        $property = Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => 'Dirección de prueba',
            'active' => true,
        ]);
        $connectionType = ConnectionType::query()->create(['name' => 'Agua']);
        $connectionStatus = ConnectionStatus::query()->create(['name' => 'ACTIVE']);

        return [
            Connection::query()->create([
                'property_id' => $property->id,
                'connection_type_id' => $connectionType->id,
                'connection_status_id' => $connectionStatus->id,
            ]),
            Connection::query()->create([
                'property_id' => $property->id,
                'connection_type_id' => $connectionType->id,
                'connection_status_id' => $connectionStatus->id,
                'payment_mode' => Connection::PAYMENT_METERED,
            ]),
        ];
    }
}
