<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\JassPageController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SpanishRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpanishRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName = 'ADMINISTRATOR', array $permissions = []): User
    {
        $role = Role::query()->create(['name' => $roleName]);
        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['name' => $name]));
        }

        return User::query()->create(['name' => 'Prueba', 'email' => fake()->unique()->safeEmail(), 'password' => 'test-password', 'role_id' => $role->id, 'active' => true]);
    }

    public function test_every_web_resource_has_a_spanish_address_and_the_pages_work(): void
    {
        $this->assertEqualsCanonicalizing(JassPageController::resourceSlugs(), array_keys(SpanishRoutes::RESOURCES));
        $this->actingAs($this->user());
        foreach (SpanishRoutes::RESOURCES as $resource => $slug) {
            $url = route('resources.index', ['resource' => $resource]);
            $this->assertStringEndsWith('/gestion/'.$slug, $url);
            $response = $this->get($url);
            if ($resource === 'late-fee-settings') {
                $response->assertRedirect(route('resources.index', ['resource' => 'settings']));
            } else {
                $response->assertOk();
            }
        }
        $this->assertStringEndsWith('/gestion/clientes/registrar', route('resources.create', ['resource' => 'customers']));
        $this->assertStringEndsWith('/gestion/clientes/123/editar', route('resources.edit', ['resource' => 'customers', 'record' => 123]));
        $this->assertStringEndsWith('/gestion/configuraciones/mora/editar', route('settings.mora.edit'));
    }

    public function test_saved_english_addresses_redirect_without_losing_search_filters(): void
    {
        $this->actingAs($this->user());
        $this->get('/gestion/customers?q=Prueba')->assertStatus(301)->assertRedirect('/gestion/clientes?q=Prueba');
        $this->get('/gestion/customers/create')->assertStatus(301)->assertRedirect('/gestion/clientes/registrar');
        $this->get('/gestion/customers/123/edit')->assertStatus(301)->assertRedirect('/gestion/clientes/123/editar');
        $this->get('/gestion/settings/mora/editar')->assertStatus(301)->assertRedirect('/gestion/configuraciones/mora/editar');
    }

    public function test_translated_report_links_still_generate_the_document(): void
    {
        $this->actingAs($this->user());
        $url = route('reports.download', ['report' => 'cash-flow', 'format' => 'pdf', 'month' => '2026-10']);
        $this->assertStringContainsString('/reportes/flujo-de-caja/pdf', $url);
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/reportes/cash-flow/pdf?month=2026-10')->assertStatus(301)->assertRedirect('/reportes/flujo-de-caja/pdf?month=2026-10');
    }

    public function test_spanish_registration_and_editing_addresses_preserve_read_only_permissions(): void
    {
        $this->actingAs($this->user('AUDITOR', ['customers.view']));
        $this->get('/gestion/clientes')->assertOk();
        $this->get('/gestion/clientes/registrar')->assertForbidden();
        $this->get('/gestion/clientes/123/editar')->assertForbidden();
        $this->post('/gestion/clientes', ['first_name' => 'No autorizado'])->assertForbidden();
    }
}
