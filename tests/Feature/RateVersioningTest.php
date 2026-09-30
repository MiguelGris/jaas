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
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\UsageType;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_rate_version_preserves_previous_invoices_and_applies_only_from_its_start_date(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-rates@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $usageType = UsageType::query()->create(['name' => 'RESIDENCIAL']);
        $originalRate = Rate::query()->create([
            'usage_type_id' => $usageType->id,
            'year' => 2026,
            'amount' => 15,
            'metered_unit_price' => 1,
            'starts_on' => '2026-01-01',
            'approved_by_assembly' => true,
        ]);
        BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral']);

        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Tarifa',
            'customer_status_id' => CustomerStatus::query()->create(['name' => 'ACTIVO'])->id,
            'registered_on' => '2026-01-01',
        ]);
        $property = Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => Neighborhood::query()->create(['name' => 'Barrio', 'active' => true])->id,
            'address' => 'Dirección de prueba',
            'active' => true,
        ]);
        Connection::query()->create([
            'property_id' => $property->id,
            'connection_type_id' => ConnectionType::query()->create(['name' => 'Agua'])->id,
            'connection_status_id' => ConnectionStatus::query()->create(['name' => 'ACTIVO'])->id,
            'installed_on' => '2026-01-01',
        ]);

        $this->assertSame(1, app(BillingService::class)->generateForMonth('2026-06'));
        $juneInvoice = Invoice::query()->whereDate('period_starts_on', '2026-06-01')->firstOrFail();
        $this->assertSame('15.00', $juneInvoice->rate);

        $this->actingAs($administrator)
            ->put(route('resources.update', ['resource' => 'rates', 'record' => $originalRate->id]), [
                'usage_type_id' => $usageType->id,
                'year' => 2026,
                'amount' => 20,
                'metered_unit_price' => 1.5,
                'starts_on' => '2026-07-01',
                'ends_on' => null,
                'approved_by_assembly' => true,
                'notes' => 'Nueva tarifa aprobada',
            ])
            ->assertSessionHasNoErrors();

        $originalRate->refresh();
        $newRate = Rate::query()->whereKeyNot($originalRate->id)->firstOrFail();
        $this->assertSame('15.00', $originalRate->amount);
        $this->assertSame('2026-01-01', $originalRate->starts_on->toDateString());
        $this->assertSame('2026-06-30', $originalRate->ends_on->toDateString());
        $this->assertSame('20.00', $newRate->amount);
        $this->assertSame('2026-07-01', $newRate->starts_on->toDateString());
        $this->assertNull($newRate->ends_on);

        // La cuota ya emitida conserva el importe con el que fue creada.
        $this->assertSame('15.00', $juneInvoice->refresh()->rate);
        $this->assertSame('15.00', $juneInvoice->total);

        $this->assertSame(1, app(BillingService::class)->generateForMonth('2026-07'));
        $julyInvoice = Invoice::query()->whereDate('period_starts_on', '2026-07-01')->firstOrFail();
        $this->assertSame('20.00', $julyInvoice->rate);
        $this->assertSame('20.00', $julyInvoice->total);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'table_name' => 'rates',
            'record_id' => $originalRate->id,
            'action' => 'UPDATE',
        ]);
    }

    public function test_rate_versions_cannot_overlap_or_be_deleted(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-rate-validation@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $usageType = UsageType::query()->create(['name' => 'COMERCIAL']);
        $rate = Rate::query()->create([
            'usage_type_id' => $usageType->id,
            'year' => 2026,
            'amount' => 30,
            'starts_on' => '2026-01-01',
        ]);

        $this->actingAs($administrator)
            ->put(route('resources.update', ['resource' => 'rates', 'record' => $rate->id]), [
                'usage_type_id' => $usageType->id,
                'year' => 2026,
                'amount' => 35,
                'metered_unit_price' => null,
                'starts_on' => '2026-01-01',
                'ends_on' => null,
                'approved_by_assembly' => false,
                'notes' => null,
            ])
            ->assertSessionHasErrors('starts_on');

        $this->actingAs($administrator)
            ->delete(route('resources.destroy', ['resource' => 'rates', 'record' => $rate->id]))
            ->assertStatus(405);

        $this->assertDatabaseCount('rates', 1);
        $this->assertDatabaseHas('rates', ['id' => $rate->id, 'amount' => 30, 'ends_on' => null]);
    }
}
