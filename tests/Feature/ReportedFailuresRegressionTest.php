<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\ConnectionUsageType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Fine;
use App\Models\Neighborhood;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Property;
use App\Models\Role;
use App\Models\UsageType;
use App\Models\User;
use App\Services\DebtService;
use App\Services\OperationalRecordService;
use App\Services\PaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportedFailuresRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'ADMINISTRATOR', array $permissions = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => $role]);
        foreach ($permissions as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::query()->firstOrCreate(['name' => $name])->id);
        }

        return User::query()->create(['name' => 'Prueba', 'email' => fake()->unique()->safeEmail(), 'password' => 'test-password', 'role_id' => $role->id, 'active' => true]);
    }

    private function connection(): Connection
    {
        UsageType::query()->firstOrCreate(['name' => 'RESIDENCIAL']);
        $customer = Customer::query()->create(['first_name' => 'Cliente', 'last_name' => 'Prueba', 'registered_on' => '2026-01-01', 'customer_status_id' => CustomerStatus::query()->firstOrCreate(['name' => 'ACTIVO'])->id]);
        $property = Property::query()->create(['customer_id' => $customer->id, 'neighborhood_id' => Neighborhood::query()->firstOrCreate(['name' => 'Sector'])->id, 'address' => 'Calle de prueba', 'active' => true]);

        return Connection::query()->create(['property_id' => $property->id, 'connection_type_id' => ConnectionType::query()->firstOrCreate(['name' => 'AGUA'])->id, 'connection_status_id' => ConnectionStatus::query()->firstOrCreate(['name' => 'ACTIVO'])->id, 'installed_on' => '2026-01-01']);
    }

    private function assembly(): Assembly
    {
        return Assembly::query()->create(['assembly_type_id' => AssemblyType::query()->firstOrCreate(['name' => 'ORDINARIA'])->id, 'held_on' => '2026-09-30', 'absence_fine' => 10, 'status' => 'SCHEDULED']);
    }

    public function test_manual_attendance_updates_the_prepared_register_without_duplicates(): void
    {
        $customer = $this->connection()->property->customer;
        $assembly = $this->assembly();
        $this->actingAs($this->user());
        $attributes = ['assembly_id' => $assembly->id, 'customer_id' => $customer->id, 'attended' => true, 'notes' => 'Confirmado manualmente'];
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('resources.store', ['resource' => 'assembly-attendances']), $attributes)->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('assembly_attendances', 1);
        $this->assertDatabaseHas('assembly_attendances', $attributes);
        $this->assertDatabaseMissing('audit_logs', ['table_name' => 'assembly_attendances', 'action' => 'INSERT']);
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'assembly_attendances', 'action' => 'UPDATE']);
    }

    public function test_read_only_customer_access_has_no_write_buttons_or_write_forms(): void
    {
        $customer = $this->connection()->property->customer;
        $this->actingAs($this->user('AUDITOR', ['customers.view', 'reports.view']));
        $this->get(route('resources.index', ['resource' => 'customers']))->assertOk()->assertDontSee('Registrar cliente')->assertDontSee('>Editar</a>', false)->assertDontSee(route('resources.index', ['resource' => 'users']), false);
        $this->get(route('resources.show', ['resource' => 'customers', 'record' => $customer->id]))->assertOk()->assertDontSee('Registrar predio de este cliente')->assertDontSee('>Editar</a>', false)->assertDontSee('>Eliminar</button>', false);
        $this->get(route('resources.create', ['resource' => 'customers']))->assertForbidden();
        $this->get(route('resources.edit', ['resource' => 'customers', 'record' => $customer->id]))->assertForbidden();
        $this->get(route('resources.create', ['resource' => 'properties']))->assertForbidden();
    }

    public function test_spanish_administrator_role_has_consistent_access(): void
    {
        $this->actingAs($this->user('ADMINISTRADOR'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Registrar cliente')->assertSee('Usuarios del sistema');
        $this->get(route('resources.create', ['resource' => 'customers']))->assertOk();
    }

    public function test_reopening_cancels_pending_fines_and_closing_regenerates_them(): void
    {
        $customer = $this->connection()->property->customer;
        $assembly = $this->assembly();
        $service = app(OperationalRecordService::class);
        $assembly = $service->assembly($assembly, ['status' => 'HELD']);
        $fine = Fine::query()->firstOrFail();
        $this->assertSame(10.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $assembly = $service->assembly($assembly, ['status' => 'SCHEDULED']);
        $this->assertSame('CANCELLED', $fine->fresh()->status);
        $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $service->assembly($assembly, ['status' => 'HELD']);
        $this->assertDatabaseCount('fines', 1);
        $this->assertSame('PENDING', $fine->fresh()->status);
    }

    public function test_paid_fines_prevent_reopening_without_changing_the_receipt(): void
    {
        $customer = $this->connection()->property->customer;
        $assembly = app(OperationalRecordService::class)->assembly($this->assembly(), ['status' => 'HELD']);
        $fine = Fine::query()->firstOrFail();
        $payment = app(PaymentCollectionService::class)->collect($customer, [], [$fine->id], ['payment_method_id' => PaymentMethod::query()->create(['name' => 'EFECTIVO'])->id], null);
        try {
            app(OperationalRecordService::class)->assembly($assembly, ['status' => 'SCHEDULED']);
            $this->fail('Debe rechazar una reapertura con cobros activos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('HELD', $assembly->fresh()->status);
        $this->assertSame('PAID', $fine->fresh()->status);
        $this->assertSame(Payment::STATUS_ACTIVE, $payment->fresh()->status);
        $this->assertSame(10.0, (float) $payment->fresh()->amount);
    }

    public function test_old_pending_fine_from_a_scheduled_assembly_cannot_be_collected(): void
    {
        $customer = $this->connection()->property->customer;
        $assembly = $this->assembly();
        $fine = Fine::query()->create(['assembly_id' => $assembly->id, 'customer_id' => $customer->id, 'amount' => 10, 'generated_on' => '2026-09-30', 'status' => 'PENDING']);
        $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        try {
            app(PaymentCollectionService::class)->collect($customer, [], [$fine->id], ['payment_method_id' => PaymentMethod::query()->create(['name' => 'EFECTIVO'])->id], null);
            $this->fail('No debe cobrar multas de una asamblea programada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fines', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_duplicate_usage_is_a_validation_error_and_existing_usage_is_linked(): void
    {
        $connection = $this->connection();
        $usage = $connection->usageAssignments()->firstOrFail();
        $this->actingAs($this->user());
        $this->post(route('resources.store', ['resource' => 'connection-usage-types']), $usage->only(['connection_id', 'usage_type_id']) + ['starts_on' => '2026-01-01'])->assertSessionHasErrors('starts_on')->assertRedirect();
        $this->assertDatabaseCount('connection_usage_types', 1);
        $this->get(route('resources.show', ['resource' => 'connections', 'record' => $connection->id]))->assertOk()->assertSee(route('resources.edit', ['resource' => 'connection-usage-types', 'record' => $usage->id]), false)->assertSee('automáticamente');
    }

    public function test_usage_dates_must_not_overlap_and_adjacent_dates_are_allowed(): void
    {
        $connection = $this->connection();
        $usage = $connection->usageAssignments()->firstOrFail();
        $service = app(OperationalRecordService::class);
        $service->usage(['ends_on' => '2026-01-31'], $usage);
        foreach ([['starts_on' => '2026-01-31', 'ends_on' => '2026-02-28'], ['starts_on' => '2026-03-01', 'ends_on' => '2026-02-28']] as $dates) {
            try {
                $service->usage($usage->only(['connection_id', 'usage_type_id']) + $dates);
                $this->fail('Debe rechazar fechas inválidas.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $service->usage($usage->only(['connection_id', 'usage_type_id']) + ['starts_on' => '2026-02-01', 'ends_on' => null]);
        $this->assertDatabaseCount('connection_usage_types', 2);
    }

    public function test_server_errors_hide_debug_details_and_return_an_incident_code(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        Route::get('/qa-error', fn () => throw new \RuntimeException('SQLSTATE confidential-session-value'));
        Route::get('/api/qa-error', fn () => throw new \RuntimeException('SQLSTATE confidential-session-value'));
        $this->get('/qa-error')->assertStatus(500)->assertSee('No pudimos completar')->assertSee('código')->assertDontSee('SQLSTATE')->assertDontSee('confidential-session-value');
        $this->getJson('/api/qa-error')->assertStatus(500)->assertJsonStructure(['message', 'incident'])->assertDontSee('SQLSTATE');
    }

    public function test_legacy_repair_is_explicit_audited_and_repeatable(): void
    {
        $administrator = $this->user();
        $connection = $this->connection();
        $usage = $connection->usageAssignments()->firstOrFail();
        ConnectionUsageType::query()->create($usage->only(['connection_id', 'usage_type_id', 'starts_on', 'ends_on']));
        $assembly = $this->assembly();
        $fine = Fine::query()->create(['assembly_id' => $assembly->id, 'customer_id' => $connection->property->customer_id, 'amount' => 10, 'generated_on' => '2026-09-30', 'status' => 'PENDING']);
        $this->artisan('jass:repair-operational-records')->assertSuccessful();
        $this->assertDatabaseCount('connection_usage_types', 2);
        $this->assertSame('PENDING', $fine->fresh()->status);
        $this->artisan('jass:repair-operational-records', ['--apply' => true, '--user' => $administrator->id])->assertSuccessful();
        $this->assertDatabaseCount('connection_usage_types', 1);
        $this->assertSame('CANCELLED', $fine->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'connection_usage_types', 'action' => 'DELETE']);
        $this->artisan('jass:repair-operational-records', ['--apply' => true, '--user' => $administrator->id])->expectsOutput('Reparación aplicada: usos duplicados 0; multas pendientes 0.')->assertSuccessful();
    }
}
