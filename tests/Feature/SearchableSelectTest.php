<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Neighborhood;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_relation_selectors_use_the_shared_autocomplete(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-searchable@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $status = CustomerStatus::query()->create(['name' => 'ACTIVO']);
        $firstCustomer = Customer::query()->create([
            'first_name' => 'Ana María',
            'last_name' => 'Quispe Flores',
            'national_id' => '12345678',
            'customer_status_id' => $status->id,
            'registered_on' => '2026-01-01',
        ]);
        Customer::query()->create([
            'first_name' => 'Ana María',
            'last_name' => 'Quispe Flores',
            'national_id' => '87654321',
            'customer_status_id' => $status->id,
            'registered_on' => '2026-01-01',
        ]);
        $neighborhood = Neighborhood::query()->create(['name' => 'Barrio Centro', 'active' => true]);
        $connectionType = ConnectionType::query()->create(['name' => 'Domiciliaria']);
        $assembly = Assembly::query()->create([
            'assembly_type_id' => AssemblyType::query()->create(['name' => 'Ordinaria'])->id,
            'held_on' => '2026-10-15',
            'held_at' => '18:00',
            'place' => 'Local comunal',
            'status' => 'SCHEDULED',
        ]);

        $this->actingAs($administrator)
            ->get(route('resources.create', ['resource' => 'properties']))
            ->assertOk()
            ->assertSee('data-searchable-select', false)
            ->assertSee('id="customer_id" type="hidden"', false)
            ->assertSee('Mostrar todas las opciones')
            ->assertSee($firstCustomer->customer_code)
            ->assertSee('12345678')
            ->assertSee('87654321')
            ->assertSee('id="neighborhood_id" name="neighborhood_id"', false)
            ->assertSee($neighborhood->name)
            ->assertDontSee('id="neighborhood_id-search"', false)
            ->assertDontSee('data-select-filter', false);

        $this->actingAs($administrator)
            ->get(route('resources.create', ['resource' => 'connections']))
            ->assertOk()
            ->assertSee('id="connection_type_id" name="connection_type_id"', false)
            ->assertSee($connectionType->name)
            ->assertDontSee('id="connection_type_id-search"', false)
            ->assertSee('id="property_id" type="hidden"', false);

        $this->actingAs($administrator)
            ->get(route('collections.create'))
            ->assertOk()
            ->assertSee('id="collection-customer-search"', false)
            ->assertSee('DNI 12345678')
            ->assertSee('DNI 87654321')
            ->assertDontSee('>Resultados<', false);

        $this->actingAs($administrator)
            ->get(route('attendance.scanner'))
            ->assertOk()
            ->assertSee('id="assembly_id" name="assembly_id"', false)
            ->assertDontSee('id="attendance-assembly-search"', false)
            ->assertSee($assembly->assembly_code)
            ->assertSee('Local comunal');

        foreach (range(1, 20) as $day) {
            Assembly::query()->create([
                'assembly_type_id' => $assembly->assembly_type_id,
                'held_on' => '2026-11-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'place' => 'Sector '.$day,
                'status' => 'SCHEDULED',
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('attendance.scanner'))
            ->assertOk()
            ->assertSee('id="attendance-assembly-search"', false)
            ->assertSee('id="assembly_id" type="hidden"', false);
    }
}
