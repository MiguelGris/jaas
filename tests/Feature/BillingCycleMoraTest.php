<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\LateFeeSetting;
use App\Models\Neighborhood;
use App\Models\PaymentMethod;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UsageType;
use App\Models\User;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use App\Services\DebtService;
use App\Services\PaymentCancellationService;
use App\Services\PaymentCollectionService;
use App\Services\PaymentConceptService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BillingCycleMoraTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_cycle_preview_and_generation_are_complete_and_idempotent(): void
    {
        [$user] = $this->context(2);
        $this->actingAs($user)->get(route('billing.index', ['month' => '2026-02', 'scope' => 'cycle']))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['ready'] === 6 && count($summary['rows']) === 6);
        $this->assertDatabaseCount('invoices', 0);
        $data = ['month' => '2026-02', 'scope' => 'cycle', 'reason' => 'Emisión del primer ciclo'];
        $this->post(route('billing.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoices', 6);
        $this->assertSame(['2026-01', '2026-02', '2026-03'], Invoice::query()->orderBy('period_starts_on')->get()->map(fn ($invoice) => $invoice->period_starts_on->format('Y-m'))->unique()->values()->all());
        $this->post(route('billing.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoices', 6);
        $customer = Customer::query()->firstOrFail();
        $this->get(route('resources.show', ['resource' => 'customers', 'record' => $customer->id]))->assertOk()->assertSee('Ficha del titular')->assertSee('Deuda pendiente');
        $this->get(route('resources.index', ['resource' => 'audit-logs', 'q' => 'Cuotas']))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 6 && $records->every(fn ($record) => $record->table_name === 'invoices'));
    }

    public static function durations(): array
    {
        return [[1], [4], [8], [5], [13]];
    }

    #[DataProvider('durations')]
    public function test_ten_clients_have_two_complete_continuous_cycles_and_only_one_mora_per_cycle(int $months): void
    {
        [$user, $customers] = $this->context(10);
        $setting = Setting::query()->where('key', 'billing_period_months')->firstOrFail();
        app(BillingCycleService::class)->saveSetting(['value' => (string) $months], $setting, '2026-01', $user);
        $billing = app(BillingService::class);
        for ($i = 0; $i < $months * 2; $i++) {
            $month = Carbon::parse('2026-01-01')->addMonthsNoOverflow($i);
            $this->assertSame(10, $billing->previewForMonth($month)['ready']);
            $this->assertSame(10, $billing->generateForMonth($month));
            $this->assertSame(0, $billing->generateForMonth($month));
        }
        $this->assertSame($months * 20, Invoice::query()->count());
        $cycles = Invoice::query()->get()->groupBy(fn ($invoice) => $invoice->cycleKey());
        $this->assertCount(20, $cycles);
        foreach ($cycles as $invoices) {
            $this->assertCount($months, $invoices);
            $this->assertCount(1, $invoices->pluck('due_on')->unique());
            $this->assertCount(1, $invoices->pluck('late_fee_setting_id')->unique());
        }
        $firstEnd = Carbon::parse('2026-01-01')->addMonthsNoOverflow($months)->subDay();
        $second = Invoice::query()->whereDate('period_starts_on', $firstEnd->copy()->addDay())->firstOrFail();
        $this->assertSame($firstEnd->copy()->addDay()->toDateString(), $second->cycle_starts_on->toDateString());
        $due = $firstEnd->copy()->addMonthNoOverflow()->endOfMonth();
        foreach ($customers as $customer) {
            $this->assertSame(0.0, app(DebtService::class)->delinquentForCustomer($customer, $due)['total']);
            $charges = app(DebtService::class)->delinquentForCustomer($customer, $due->copy()->addDay());
            $this->assertSame((float) ($months * 15 + 2), $charges['total']);
            $this->assertSame(2.0, (float) $charges['invoices']->sum('calculated_late_fee'));
        }
    }

    public function test_changed_duration_begins_after_active_cycle_and_retains_previous_dates(): void
    {
        [$user] = $this->context();
        $billing = app(BillingService::class);
        $billing->generateForMonth('2026-01');
        $original = Invoice::query()->firstOrFail()->getAttributes();
        $setting = Setting::query()->where('key', 'billing_period_months')->firstOrFail();
        $this->actingAs($user)->put(route('resources.update', ['resource' => 'settings', 'record' => $setting->id]), ['key' => $setting->key, 'value' => '3', 'description' => 'Sin cambiar la duración'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('billing_cycle_schedules', 0);
        $this->travelTo(Carbon::parse('2026-02-10'));
        $this->actingAs($user)->put(route('resources.update', ['resource' => 'settings', 'record' => $setting->id]), ['key' => $setting->key, 'value' => '8'])->assertSessionHasNoErrors();
        $this->putJson('/api/v1/settings/'.$setting->id, ['value' => '8'])->assertOk();
        $this->assertDatabaseCount('billing_cycle_schedules', 2);
        $cycles = app(BillingCycleService::class);
        $this->assertSame(3, $cycles->forMonth('2026-03')['months']);
        $this->assertSame('2026-04-01', $cycles->forMonth('2026-04')['start']->toDateString());
        $this->assertSame('2026-11-30', $cycles->forMonth('2026-04')['end']->toDateString());
        $this->assertSame('2026-12-01', $cycles->forMonth('2027-01')['start']->toDateString());
        $this->assertSame('2027-07-31', $cycles->forMonth('2027-01')['end']->toDateString());
        $billing->generateForMonth('2026-02');
        $this->assertSame($original, Invoice::query()->firstOrFail()->getAttributes());
        $this->assertSame('2026-04-30', Invoice::query()->latest('id')->first()->due_on->toDateString());
        $this->get(route('resources.edit', ['resource' => 'settings', 'record' => $setting->id]))->assertOk()->assertSee('type="month"', false)->assertSee('Desde 04/2026: ciclos de 8');
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'billing_cycle_schedules', 'action' => 'INSERT']);
    }

    public function test_optional_start_and_api_validation_preserve_complete_cycles(): void
    {
        [$user] = $this->context();
        $setting = Setting::query()->where('key', 'billing_period_months')->firstOrFail();
        $url = '/api/v1/settings/'.$setting->id;
        $this->actingAs($user);
        foreach (['0', '-1', '1.5', 'abc'] as $value) {
            $this->putJson($url, ['value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
        }
        $this->putJson($url, ['key' => 'renamed', 'value' => '5'])->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->putJson($url, ['value' => '5', 'cycle_start_month' => '2026-02'])->assertOk();
        $this->assertSame('2026-06-30', app(BillingCycleService::class)->forMonth('2026-02')['end']->toDateString());
        $this->travelTo(Carbon::parse('2026-03-01'));
        app(BillingService::class)->generateForMonth('2026-02');
        $this->putJson($url, ['value' => '1', 'cycle_start_month' => '2026-05'])->assertUnprocessable()->assertJsonValidationErrors('cycle_start_month');
        $this->putJson($url, ['value' => '1'])->assertOk();
        $this->assertSame('2026-07-01', app(BillingCycleService::class)->forMonth('2026-07')['start']->toDateString());
        $this->assertSame('2026-07-31', app(BillingCycleService::class)->forMonth('2026-07')['end']->toDateString());
    }

    public function test_mora_versions_do_not_reprice_existing_cycles_and_cannot_rewrite_history(): void
    {
        [$user, $customers, $fee] = $this->context();
        $billing = app(BillingService::class);
        $billing->generateForMonth('2026-01');
        $this->actingAs($user)->putJson('/api/v1/late-fee-settings/'.$fee->id, ['monthly_amount' => 5, 'grace_months' => 0, 'starts_on' => '2026-02-01'])->assertOk();
        $newFee = LateFeeSetting::query()->latest('id')->firstOrFail();
        $this->assertSame('2.00', $fee->fresh()->monthly_amount);
        $this->assertSame('2026-01-31', $fee->fresh()->ends_on->toDateString());
        foreach (['2026-02', '2026-03', '2026-04'] as $month) {
            $billing->generateForMonth($month);
        }
        $oldCycle = Invoice::query()->where('late_fee_setting_id', $fee->id)->get();
        $this->assertCount(3, $oldCycle);
        $this->assertCount(1, $oldCycle->pluck('due_on')->unique());
        $this->assertSame('2026-04-30', $oldCycle->last()->due_on->toDateString());
        $april = Invoice::query()->where('late_fee_setting_id', $newFee->id)->firstOrFail();
        $this->assertSame('2026-06-30', $april->due_on->toDateString());
        $this->assertSame('5.00', $april->cycle_late_fee_amount);
        $charges = app(DebtService::class)->delinquentForCustomer($customers->first(), '2026-05-01');
        $this->assertSame(47.0, $charges['total']);
        $this->putJson('/api/v1/late-fee-settings/'.$fee->id, ['monthly_amount' => 9, 'starts_on' => '2027-01-01'])->assertUnprocessable();
        $this->putJson('/api/v1/late-fee-settings/'.$newFee->id, ['starts_on' => '2026-01-01'])->assertUnprocessable();
        $this->get(route('settings.mora.edit'))->assertOk()->assertSee('Historial de versiones de mora')->assertSee('Crear nueva versión');
        $this->assertDatabaseCount('late_fee_settings', 2);
    }

    public function test_split_payments_charge_only_incremental_cycle_mora_and_annulment_restores_exact_debt(): void
    {
        [$user, $customers, , $method] = $this->context();
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $ids = Invoice::query()->orderBy('period_starts_on')->pluck('id');
        $customer = $customers->first();
        $collect = app(PaymentCollectionService::class);
        $details = ['payment_method_id' => $method->id];
        $this->travelTo(Carbon::parse('2026-05-01 12:00:00'));
        $first = $collect->collect($customer, [$ids[0]], [], $details, $user);
        $this->assertSame('17.00', $first->amount);
        $this->assertSame(30.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));
        $this->assertSame(32.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $second = $collect->collect($customer, [$ids[2], $ids[1]], [], $details, $user);
        $this->assertSame('32.00', $second->amount);
        $this->assertSame(4.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['late_fees']);
        $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer, '2027-01-01')['total']);
        $before = Invoice::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->assertSame(0, app(BillingService::class)->recalculateDeadlines());
        $this->assertSame($before, Invoice::query()->orderBy('id')->get()->map->getAttributes()->all());
        app(PaymentCancellationService::class)->cancel($first, $user, 'Corrección de prueba');
        $this->assertSame(17.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $third = $collect->collect($customer, [$ids[0]], [], $details, $user);
        $this->assertSame('17.00', $third->amount);
        $this->assertSame(4.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['late_fees']);
        $concept = collect(app(PaymentConceptService::class)->forPayment($third))->firstWhere('category', 'Mora');
        $this->assertStringContainsString('Mora del ciclo', $concept['detail']);
        $this->assertSame(2.0, $concept['amount']);
    }
}
