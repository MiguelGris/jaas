<?php

namespace Tests\Feature;

use App\Models\LateFeeSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::query()->create(['name' => 'Admin', 'email' => 'settings@example.test', 'password' => 'test-password', 'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id, 'active' => true]);
    }

    public function test_settings_list_has_a_mora_row_and_a_read_only_manual_run_with_plain_descriptions(): void
    {
        foreach (['Período de Pago de agua', 'Día de generación de recibos', 'Período de gracia', 'payment_due_days'] as $legacyKey) {
            Setting::query()->create(['key' => $legacyKey, 'value' => '3']);
        }
        Setting::query()->create(['key' => 'Periodo de facturación', 'value' => '3', 'description' => 'Solo 3 o 6 meses.']);
        $automatic = Setting::query()->create(['key' => 'billing_last_manual_run', 'value' => '2026-10-05 · 2026-10 · 10 cuotas', 'description' => 'Ver billing_last_manual_run en settings']);
        Setting::query()->updateOrCreate(['key' => 'billing_period_months'], ['value' => '3', 'description' => 'Configura billing_periods y late_fee_settings']);
        $this->actingAs($this->administrator());
        $response = $this->get(route('resources.index', ['resource' => 'settings']));
        $response->assertOk()->assertSee('Configuración de mora')->assertSee('Última emisión manual')->assertSee('Ciclo de pago')->assertSee('Automático')
            ->assertDontSee('billing_last_manual_run')->assertDontSee('billing_period_months')->assertDontSee('late_fee_settings')->assertDontSee('Solo 3 o 6 meses')->assertDontSee('Periodo De Facturación')->assertDontSee('Período De Pago De Agua')->assertDontSee('Día De Generación De Recibos')->assertDontSee('Período De Gracia')->assertDontSee('Plazo de pago anterior')->assertSee('Día de emisión de cuotas')
            ->assertDontSee(route('resources.edit', ['resource' => 'settings', 'record' => $automatic->id]), false);
        $this->assertMatchesRegularExpression('/<tr[^>]*>\s*<td[^>]*>Configuración de mora<\/td>/', $response->getContent());
        $this->get(route('resources.show', ['resource' => 'settings', 'record' => $automatic->id]))->assertOk()->assertDontSee('>Editar</a>', false)->assertDontSee('>Eliminar</button>', false)->assertDontSee('billing_last_manual_run');
    }

    public function test_automatic_setting_is_protected_from_direct_web_and_api_writes(): void
    {
        $automatic = Setting::query()->create(['key' => 'billing_last_manual_run', 'value' => 'Última emisión']);
        $this->actingAs($this->administrator());
        $this->get(route('resources.edit', ['resource' => 'settings', 'record' => $automatic->id]))->assertForbidden();
        $this->put(route('resources.update', ['resource' => 'settings', 'record' => $automatic->id]), ['key' => $automatic->key, 'value' => 'Modificado'])->assertSessionHasErrors('key');
        $this->delete(route('resources.destroy', ['resource' => 'settings', 'record' => $automatic->id]))->assertForbidden();
        $this->putJson('/api/v1/settings/'.$automatic->id, ['value' => 'Modificado'])->assertForbidden();
        $this->deleteJson('/api/v1/settings/'.$automatic->id)->assertForbidden();
        $this->assertSame('Última emisión', $automatic->fresh()->value);
    }

    public function test_mora_can_be_edited_directly_and_settings_are_alphabetical(): void
    {
        $fee = LateFeeSetting::query()->create(['monthly_amount' => 2, 'grace_days' => 30, 'grace_months' => 1, 'starts_on' => '2026-01-01']);
        $this->actingAs($this->administrator());
        $this->get(route('resources.index', ['resource' => 'settings']))->assertOk()
            ->assertSeeInOrder(['Ciclo de pago', 'Configuración de mora', 'Día de emisión de cuotas'])
            ->assertSee(route('settings.mora.edit'), false)
            ->assertSee('S/ 2.00 al mes');
        $this->get(route('resources.edit', ['resource' => 'late-fee-settings', 'record' => $fee->id]))->assertOk();
        $this->put(route('resources.update', ['resource' => 'late-fee-settings', 'record' => $fee->id]), ['monthly_amount' => 3, 'grace_months' => 2, 'starts_on' => '2027-01-01', 'ends_on' => null])->assertSessionHasNoErrors()->assertRedirect(route('resources.index', ['resource' => 'settings']));
        $this->assertDatabaseHas('late_fee_settings', ['monthly_amount' => 3, 'grace_months' => 2]);
        $this->assertSame('2027-01-01', LateFeeSetting::query()->latest('id')->firstOrFail()->starts_on->toDateString());
        $this->assertSame('2.00', $fee->fresh()->monthly_amount);
    }

    public function test_all_configuration_rows_are_protected_from_deletion(): void
    {
        $setting = Setting::query()->where('key', 'billing_period_months')->firstOrFail();
        $fee = LateFeeSetting::query()->create(['monthly_amount' => 2, 'grace_days' => 30, 'grace_months' => 1, 'starts_on' => '2026-01-01']);
        $this->actingAs($this->administrator());
        foreach (['settings' => $setting, 'late-fee-settings' => $fee] as $resource => $record) {
            $this->get(route('resources.show', ['resource' => $resource, 'record' => $record->id]))->assertOk()->assertDontSee('>Eliminar</button>', false);
            $this->delete(route('resources.destroy', ['resource' => $resource, 'record' => $record->id]))->assertForbidden();
            $this->deleteJson('/api/v1/'.$resource.'/'.$record->id)->assertForbidden();
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
        }
    }

    public function test_mora_navigation_returns_to_the_general_list_without_an_intermediate_list(): void
    {
        LateFeeSetting::query()->create(['monthly_amount' => 2, 'grace_days' => 30, 'grace_months' => 1, 'starts_on' => '2026-01-01']);
        $this->actingAs($this->administrator());
        $this->get(route('resources.index', ['resource' => 'late-fee-settings']))->assertRedirect(route('resources.index', ['resource' => 'settings']));
        $this->get(route('settings.mora.edit'))->assertOk()->assertSee(route('resources.index', ['resource' => 'settings']), false)->assertSee('Crear nueva versión');
        $this->get(route('settings.mora.show'))->assertOk()->assertSee(route('resources.index', ['resource' => 'settings']), false);
    }

    public function test_mora_only_permission_exposes_only_mora_in_the_general_list(): void
    {
        $role = Role::query()->create(['name' => 'ACCOUNTING']);
        $role->permissions()->attach(Permission::query()->create(['name' => 'rates.manage']));
        $user = User::query()->create(['name' => 'Responsable de mora', 'email' => 'mora-only@example.test', 'password' => 'test-password', 'role_id' => $role->id, 'active' => true]);
        $this->actingAs($user);
        $this->get(route('resources.index', ['resource' => 'settings']))->assertOk()->assertSee('Configuración de mora')->assertDontSee('Ciclo de pago')->assertDontSee('Registrar configuración');
        $this->get(route('settings.mora.edit'))->assertOk();
        $setting = Setting::query()->where('key', 'billing_period_months')->firstOrFail();
        $this->get(route('resources.show', ['resource' => 'settings', 'record' => $setting->id]))->assertForbidden();
        $this->get(route('resources.edit', ['resource' => 'settings', 'record' => $setting->id]))->assertForbidden();
    }
}
