<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Meter;
use App\Models\Neighborhood;
use App\Models\Property;
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
