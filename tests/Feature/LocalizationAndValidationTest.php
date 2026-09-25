<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationAndValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_validation_messages_are_shown_in_spanish(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
    }

    public function test_required_fields_cannot_be_cleared_when_editing_a_record(): void
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'last_name' => 'JASS',
            'email' => 'admin-validation@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
        $managedUser = User::query()->create([
            'name' => 'Nombre original',
            'last_name' => 'Prueba',
            'email' => 'managed-validation@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);

        $response = $this->actingAs($administrator)
            ->from(route('resources.edit', ['resource' => 'users', 'record' => $managedUser->id]))
            ->put(route('resources.update', ['resource' => 'users', 'record' => $managedUser->id]), [
                'name' => '',
                'last_name' => 'Prueba',
                'email' => $managedUser->email,
                'password' => '',
                'role_id' => $role->id,
                'active' => '1',
            ]);

        $response->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
        $this->assertSame('Nombre original', $managedUser->fresh()->name);
    }

    public function test_the_authenticated_layout_includes_the_mobile_menu_and_a_translated_role(): void
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-mobile@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="mobile-menu-toggle"', false)
            ->assertSee('Administrador');
    }
}
