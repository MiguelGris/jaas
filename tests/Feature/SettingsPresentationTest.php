<?php

namespace Tests\Feature;

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
        $automatic = Setting::query()->create(['key' => 'billing_last_manual_run', 'value' => '2026-10-05 · 2026-10 · 10 cuotas', 'description' => 'Ver billing_last_manual_run en settings']);
        Setting::query()->updateOrCreate(['key' => 'billing_period_months'], ['value' => '3', 'description' => 'Configura billing_periods y late_fee_settings']);
        $this->actingAs($this->administrator());
        $response = $this->get(route('resources.index', ['resource' => 'settings']));
        $response->assertOk()->assertSee('Configuración de mora')->assertSee('Última emisión manual')->assertSee('Ciclo de pago')->assertSee('Automático')
            ->assertDontSee('billing_last_manual_run')->assertDontSee('billing_period_months')->assertDontSee('late_fee_settings')
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
}
