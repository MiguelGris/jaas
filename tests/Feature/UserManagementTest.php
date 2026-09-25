<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_a_system_user_with_a_role(): void
    {
        $administrator = $this->user('ADMINISTRATOR', 'admin-users@example.test', 'AdminPassword1');
        $operatorRole = Role::query()->create(['name' => 'OPERATOR']);

        $response = $this->actingAs($administrator)->post(route('resources.store', ['resource' => 'users']), [
            'name' => 'Nuevo',
            'last_name' => 'Operador',
            'email' => 'operador-nuevo@example.test',
            'password' => 'TemporaryPassword1',
            'role_id' => $operatorRole->id,
            'active' => '1',
        ]);

        $response->assertRedirect(route('resources.index', ['resource' => 'users']));
        $created = User::query()->where('email', 'operador-nuevo@example.test')->firstOrFail();
        $this->assertSame($operatorRole->id, $created->role_id);
        $this->assertTrue(Hash::check('TemporaryPassword1', $created->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'table_name' => 'users',
            'record_id' => $created->id,
            'action' => 'INSERT',
        ]);
    }

    public function test_a_user_can_change_their_own_password_without_logging_the_secret(): void
    {
        $user = $this->user('CASHIER', 'cashier-password@example.test', 'OldPassword1');

        $response = $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'OldPassword1',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewPassword2', $user->fresh()->password));
        $audit = AuditLog::query()->where('record_id', $user->id)->where('action', 'UPDATE')->latest('id')->firstOrFail();
        $this->assertSame(['password' => 'PROTECTED'], $audit->old_values);
        $this->assertSame(['password' => 'UPDATED'], $audit->new_values);
    }

    private function user(string $roleName, string $email, string $password): User
    {
        $role = Role::query()->create(['name' => $roleName]);

        return User::query()->create([
            'name' => 'Usuario',
            'last_name' => 'Prueba',
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'active' => true,
        ]);
    }
}
