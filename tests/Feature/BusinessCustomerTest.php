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
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_register_a_business_with_an_eleven_digit_ruc(): void
    {
        $response = $this->actingAs($this->administrator())->post(route('resources.store', ['resource' => 'customers']), [
            'customer_type' => Customer::TYPE_BUSINESS,
            'national_id' => '20123456789',
            'business_name' => 'Bodega San Miguel E.I.R.L.',
            'customer_status_id' => $this->activeStatus()->id,
            'registered_on' => '2026-10-02',
        ]);

        $response->assertRedirect(route('resources.index', ['resource' => 'customers']));
        $this->assertDatabaseHas('customers', [
            'customer_type' => Customer::TYPE_BUSINESS,
            'national_id' => '20123456789',
            'business_name' => 'Bodega San Miguel E.I.R.L.',
            'first_name' => null,
            'last_name' => null,
            'birth_date' => null,
        ]);

        $business = Customer::query()->where('national_id', '20123456789')->firstOrFail();
        $this->propertyFor($business);

        $this->actingAs($this->administrator())
            ->get(route('resources.index', ['resource' => 'properties']))
            ->assertOk()
            ->assertSee('Bodega San Miguel E.I.R.L.');
    }

    public function test_customer_document_validation_depends_on_the_customer_type(): void
    {
        $common = [
            'customer_status_id' => $this->activeStatus()->id,
            'registered_on' => '2026-10-02',
        ];

        $this->actingAs($this->administrator())
            ->from(route('resources.create', ['resource' => 'customers']))
            ->post(route('resources.store', ['resource' => 'customers']), [
                ...$common,
                'customer_type' => Customer::TYPE_BUSINESS,
                'national_id' => '12345678',
                'business_name' => 'Negocio inválido',
            ])
            ->assertSessionHasErrors('national_id');

        $this->actingAs($this->administrator())
            ->post(route('resources.store', ['resource' => 'customers']), [
                ...$common,
                'customer_type' => Customer::TYPE_PERSON,
                'national_id' => '20123456789',
                'first_name' => 'Ana',
                'last_name' => 'Prueba',
            ])
            ->assertSessionHasErrors('national_id');

        $exemptStatus = CustomerStatus::query()->create(['name' => 'EXEMPT']);
        $this->actingAs($this->administrator())
            ->post(route('resources.store', ['resource' => 'customers']), [
                ...$common,
                'customer_type' => Customer::TYPE_BUSINESS,
                'national_id' => '20123456789',
                'business_name' => 'Negocio no exonerable',
                'customer_status_id' => $exemptStatus->id,
            ])
            ->assertSessionHasErrors('customer_status_id');
    }

    public function test_a_business_can_consult_its_pending_debt_with_its_ruc(): void
    {
        $business = Customer::query()->create([
            'customer_type' => Customer::TYPE_BUSINESS,
            'national_id' => '20123456789',
            'business_name' => 'Ferretería El Manantial S.A.C.',
            'customer_status_id' => $this->activeStatus()->id,
            'registered_on' => '2026-01-01',
        ]);
        $connection = $this->connectionFor($business);
        $invoice = Invoice::query()->create([
            'connection_id' => $connection->id,
            'billing_period_id' => BillingPeriod::query()->create(['months' => 3, 'description' => 'Trimestral'])->id,
            'issued_on' => '2026-09-28',
            'due_on' => '2026-12-31',
            'period_starts_on' => '2026-09-01',
            'period_ends_on' => '2026-09-30',
            'rate' => 25,
            'late_fee' => 0,
            'fines' => 0,
            'total' => 25,
            'status' => 'PENDING',
        ]);

        $this->post(route('debt.lookup'), ['national_id' => '20123456789'])
            ->assertOk()
            ->assertSee('Ferretería El Manantial S.A.C.')
            ->assertSee('RUC consultado: 20123456789')
            ->assertSee($invoice->period_starts_on->translatedFormat('F Y'))
            ->assertSee('25.00');
    }

    public function test_businesses_are_never_included_in_the_age_exemption_report(): void
    {
        $status = $this->activeStatus();
        $person = Customer::query()->create([
            'customer_type' => Customer::TYPE_PERSON,
            'national_id' => '12345678',
            'first_name' => 'María',
            'last_name' => 'Mayor',
            'birth_date' => '1940-01-01',
            'customer_status_id' => $status->id,
            'registered_on' => '2026-01-01',
        ]);
        $business = Customer::query()->create([
            'customer_type' => Customer::TYPE_BUSINESS,
            'national_id' => '20987654321',
            'business_name' => 'Comercial Sin Exoneración S.A.C.',
            'birth_date' => '1940-01-01',
            'customer_status_id' => $status->id,
            'registered_on' => '2026-01-01',
        ]);
        $this->propertyFor($person);
        $this->propertyFor($business);

        // Simula un dato histórico inconsistente para comprobar que el reporte
        // también excluye empresas a nivel de consulta, no solo por el modelo.
        DB::table('customers')->where('id', $business->id)->update(['birth_date' => '1940-01-01']);

        $report = app(ReportService::class)->build('work-exemptions');
        $names = collect($report['rows'])->pluck(2);

        $this->assertTrue($names->contains('María Mayor'));
        $this->assertFalse($names->contains('Comercial Sin Exoneración S.A.C.'));
    }

    private function administrator(): User
    {
        return User::query()->create([
            'name' => 'Administrador',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123',
            'role_id' => Role::query()->firstOrCreate(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
    }

    private function activeStatus(): CustomerStatus
    {
        return CustomerStatus::query()->firstOrCreate(['name' => 'ACTIVE']);
    }

    private function propertyFor(Customer $customer): Property
    {
        return Property::query()->create([
            'customer_id' => $customer->id,
            'neighborhood_id' => Neighborhood::query()->firstOrCreate(['name' => 'Centro'], ['active' => true])->id,
            'address' => 'Dirección '.$customer->id,
            'active' => true,
        ]);
    }

    private function connectionFor(Customer $customer): Connection
    {
        return Connection::query()->create([
            'property_id' => $this->propertyFor($customer)->id,
            'connection_type_id' => ConnectionType::query()->firstOrCreate(['name' => 'WATER'])->id,
            'connection_status_id' => ConnectionStatus::query()->firstOrCreate(['name' => 'ACTIVE'])->id,
        ]);
    }
}
