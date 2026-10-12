<?php

namespace Tests\Support;

use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\LateFeeSetting;
use App\Models\Neighborhood;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\UsageType;
use App\Models\User;
use Carbon\Carbon;

trait CreatesBillingContext
{
    private function context(int $clients = 1): array
    {
        $this->travelTo(Carbon::parse('2026-01-10 10:00:00'));
        $user = User::query()->create(['name' => 'Administrador', 'email' => 'cycles@example.test', 'password' => 'test-password', 'active' => true, 'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id]);
        $usage = UsageType::query()->create(['name' => 'RESIDENCIAL']);
        foreach ([2026, 2027, 2028] as $year) {
            Rate::query()->create(['usage_type_id' => $usage->id, 'year' => $year, 'amount' => 15, 'starts_on' => $year.'-01-01', 'approved_by_assembly' => true]);
        }
        $fee = LateFeeSetting::query()->create(['monthly_amount' => 2, 'grace_months' => 1, 'starts_on' => '2026-01-01']);
        $status = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $neighborhood = Neighborhood::query()->create(['name' => 'Centro', 'active' => true]);
        $type = ConnectionType::query()->create(['name' => 'Agua']);
        $connectionStatus = ConnectionStatus::query()->create(['name' => 'ACTIVE']);
        $customers = collect();
        for ($i = 0; $i < $clients; $i++) {
            $customer = Customer::query()->create(['first_name' => 'Cliente', 'last_name' => 'Ciclo '.$i, 'customer_status_id' => $status->id, 'registered_on' => '2026-01-01']);
            $property = Property::query()->create(['customer_id' => $customer->id, 'neighborhood_id' => $neighborhood->id, 'address' => 'Dirección '.$i, 'active' => true]);
            Connection::query()->create(['property_id' => $property->id, 'connection_type_id' => $type->id, 'connection_status_id' => $connectionStatus->id, 'installed_on' => '2026-01-01']);
            $customers->push($customer);
        }
        $method = PaymentMethod::query()->create(['name' => 'CASH']);

        return [$user, $customers, $fee, $method];
    }
}
