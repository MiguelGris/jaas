<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\ConnectionUsageType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UsageType;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DebtService;
use App\Services\PaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsabilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::query()->create(['name' => 'Admin', 'email' => 'usability@example.test', 'password' => 'secret-password', 'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id, 'active' => true]);
    }

    private function connection(string $status = 'ACTIVE'): Connection
    {
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'national_id' => sprintf('990000%02d', Customer::query()->count() + 1), 'customer_status_id' => CustomerStatus::query()->firstOrCreate(['name' => $status])->id, 'registered_on' => '2026-01-01']);
        $property = Property::query()->create(['customer_id' => $customer->id, 'neighborhood_id' => Neighborhood::query()->firstOrCreate(['name' => 'Prueba'], ['active' => true])->id, 'address' => 'Dirección ficticia', 'active' => true]);
        $connection = Connection::query()->create(['property_id' => $property->id, 'connection_type_id' => ConnectionType::query()->firstOrCreate(['name' => 'Agua'])->id, 'connection_status_id' => ConnectionStatus::query()->firstOrCreate(['name' => 'ACTIVE'])->id, 'installed_on' => '2026-01-01']);
        $usage = UsageType::query()->firstOrCreate(['name' => 'QA TEST']);
        ConnectionUsageType::query()->create(['connection_id' => $connection->id, 'usage_type_id' => $usage->id, 'starts_on' => '2026-01-01']);
        BillingPeriod::query()->firstOrCreate(['months' => 3], ['description' => 'Trimestral']);
        Rate::query()->firstOrCreate(['usage_type_id' => $usage->id, 'year' => 2026], ['amount' => 15, 'starts_on' => '2026-01-01']);

        return $connection;
    }

    public function test_exempt_customer_receives_water_bills_but_future_installation_does_not(): void
    {
        $connection = $this->connection('EXONERADO');
        $billing = app(BillingService::class);
        $this->assertSame(1, $billing->generateForMonth('2026-01'));
        $connection->update(['installed_on' => '2026-09-15']);
        $this->assertSame(0, $billing->generateForMonth('2026-07'));
        $this->assertStringContainsString('anterior a la instalación', $billing->previewForMonth('2026-07')['rows'][0]['reason']);
        $this->assertSame(1, $billing->generateForMonth('2026-09'));
    }

    public function test_preview_explains_missing_rate_without_writing_and_emission_is_idempotent(): void
    {
        $this->connection();
        Rate::query()->delete();
        $billing = app(BillingService::class);
        $preview = $billing->previewForMonth('2026-02');
        $this->assertSame(1, $preview['omitted']);
        $this->assertStringContainsString('Falta una tarifa', $preview['rows'][0]['reason']);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(0, $billing->generateForMonth('2026-02'));
    }

    public function test_ten_customers_have_two_complete_quarters_without_duplicate_invoices(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $connection = $this->connection();
            $connection->property->customer->update(['national_id' => sprintf('990000%02d', $i + 1)]);
        }
        $billing = app(BillingService::class);
        for ($month = 1; $month <= 6; $month++) {
            $this->assertSame(10, $billing->generateForMonth(sprintf('2026-%02d', $month)));
        }
        $this->assertSame(0, $billing->generateForMonth('2026-01'));
        $this->assertDatabaseCount('invoices', 60);
        $this->assertSame(900.0, (float) Invoice::query()->sum('total'));
        $this->assertSame(30, Invoice::query()->whereDate('due_on', '2026-04-30')->count());
        $this->assertSame(30, Invoice::query()->whereDate('due_on', '2026-07-31')->count());
        $method = PaymentMethod::query()->create(['name' => 'Efectivo']);
        foreach (Customer::query()->get() as $customer) {
            $invoices = Invoice::query()->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customer->id))->orderBy('period_starts_on')->get();
            foreach ($invoices->chunk(3) as $cycle) {
                app(PaymentCollectionService::class)->collect($customer, $cycle->modelKeys(), [], ['payment_method_id' => $method->id], null);
            }
            $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        }
        $this->assertDatabaseCount('payments', 20);
        $this->assertSame(60, Invoice::query()->where('status', 'PAID')->count());
    }

    public function test_manual_emission_is_scoped_audited_and_requires_permissions(): void
    {
        $connection = $this->connection();
        $user = $this->administrator();
        $data = ['month' => '2026-02', 'customer_id' => $connection->property->customer_id, 'reason' => 'Prueba de recuperación'];
        $this->get(route('billing.index', $data))->assertRedirect(route('login'));
        $user->update(['role_id' => Role::query()->create(['name' => 'CASHIER'])->id]);
        $this->actingAs($user)->post(route('billing.store'), $data)->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
        $user->update(['role_id' => Role::query()->where('name', 'ADMINISTRATOR')->value('id')]);
        $user->unsetRelation('role');
        $this->actingAs($user)->get(route('billing.index', $data))->assertOk()->assertSee('Confirmar generación');
        $this->assertDatabaseCount('invoices', 0);
        $this->post(route('billing.store'), $data)->assertRedirect();
        $this->post(route('billing.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame($data['reason'], AuditLog::query()->first()->new_values['emission_reason']);
        $this->assertDatabaseHas('settings', ['key' => 'billing_last_manual_run']);
    }

    public function test_invalid_payment_preserves_selections_method_and_notes_and_displays_errors(): void
    {
        $connection = $this->connection();
        app(BillingService::class)->generateForMonth('2026-01');
        $invoice = Invoice::query()->first();
        PaymentMethod::query()->create(['name' => 'Efectivo']);
        $method = PaymentMethod::query()->create(['name' => 'Transferencia']);
        $url = route('collections.create', ['customer' => $connection->property->customer_id]);
        $notes = str_repeat('x', 251);
        $this->actingAs($this->administrator())->from($url)->post(route('collections.store'), ['customer_id' => $connection->property->customer_id, 'invoices' => [$invoice->id], 'payment_method_id' => $method->id, 'notes' => $notes])->assertRedirect($url)->assertSessionHasErrors('notes')->assertSessionHasInput('notes', $notes)->assertSessionHasInput('invoices', [$invoice->id]);
        $response = $this->get($url)->assertOk()->assertSee('El pago no se registró')->assertSee($notes)->assertSee('data-select-cycle', false)->assertSee('Revisar pago antes de confirmar');
        $this->assertMatchesRegularExpression('/name="invoices\[\]" value="'.$invoice->id.'"\s+checked/', $response->getContent());
        $this->assertMatchesRegularExpression('/value="'.$method->id.'"\s+selected>Transferencia/', $response->getContent());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_search_and_guided_property_form(): void
    {
        $connection = $this->connection();
        $customer = $connection->property->customer;
        $this->actingAs($this->administrator())->get(route('resources.index', ['resource' => 'customers', 'q' => '99000001']))->assertOk()->assertSee($customer->customer_code)->assertSee('Lista de clientes para teléfono');
        $this->get(route('resources.index', ['resource' => 'customers', 'q' => 'INEXISTENTE']))->assertOk()->assertDontSee($customer->customer_code);
        $this->get(route('resources.create', ['resource' => 'properties', 'customer_id' => $customer->id]))->assertOk()->assertSee('Un predio inactivo no recibirá cuotas');
    }

    public function test_billing_settings_reject_invalid_values_and_key_renames(): void
    {
        $setting = Setting::query()->updateOrCreate(['key' => 'billing_period_months'], ['value' => '3']);
        $url = route('resources.update', ['resource' => 'settings', 'record' => $setting->id]);
        $this->actingAs($this->administrator())->put($url, ['key' => 'billing_period_months', 'value' => '0'])->assertSessionHasErrors('value');
        $this->put($url, ['key' => 'antiguo', 'value' => '6'])->assertSessionHasErrors('key');
        $this->assertSame('3', $setting->fresh()->value);
    }
}
