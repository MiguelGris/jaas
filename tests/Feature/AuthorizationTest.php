<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_scanner_requires_assembly_management_permission(): void
    {
        $reportsPermission = Permission::query()->create(['name' => 'reports.view']);
        $assembliesPermission = Permission::query()->create(['name' => 'assemblies.manage']);
        $cashierRole = Role::query()->create(['name' => 'CASHIER']);
        $cashierRole->permissions()->attach($reportsPermission);
        $operatorRole = Role::query()->create(['name' => 'OPERATOR']);
        $operatorRole->permissions()->attach($assembliesPermission);

        $cashier = $this->user($cashierRole, 'cashier-auth@example.test');
        $operator = $this->user($operatorRole, 'operator-auth@example.test');

        $this->actingAs($cashier)->get(route('attendance.scanner'))->assertForbidden();
        $this->actingAs($operator)->get(route('attendance.scanner'))->assertOk();
    }

    public function test_dashboard_quick_actions_follow_the_user_permissions(): void
    {
        $reportsPermission = Permission::query()->create(['name' => 'reports.view']);
        $customerPermission = Permission::query()->create(['name' => 'customers.create']);
        $paymentsPermission = Permission::query()->create(['name' => 'payments.create']);
        $assembliesPermission = Permission::query()->create(['name' => 'assemblies.manage']);

        $administratorRole = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $cashierRole = Role::query()->create(['name' => 'CASHIER']);
        $cashierRole->permissions()->attach([$reportsPermission->id, $paymentsPermission->id]);
        $operatorRole = Role::query()->create(['name' => 'OPERATOR']);
        $operatorRole->permissions()->attach([
            $reportsPermission->id,
            $customerPermission->id,
            $assembliesPermission->id,
        ]);

        $administrator = $this->user($administratorRole, 'admin-actions@example.test');
        $cashier = $this->user($cashierRole, 'cashier-actions@example.test');
        $operator = $this->user($operatorRole, 'operator-actions@example.test');

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Registrar cliente')
            ->assertSee('Realizar pago')
            ->assertSee('Registrar asistencia');

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Registrar cliente')
            ->assertSee('Realizar pago')
            ->assertDontSee('Registrar asistencia');

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Registrar cliente')
            ->assertDontSee('Realizar pago')
            ->assertSee('Registrar asistencia');
    }

    public function test_permission_catalog_is_hidden_and_inaccessible_even_to_administrators(): void
    {
        $permission = Permission::query()->create(['name' => 'users.manage']);
        foreach (['ADMINISTRATOR', 'OPERATOR'] as $roleName) {
            $role = Role::query()->create(['name' => $roleName]);
            $role->permissions()->attach($permission);
            $this->actingAs($this->user($role, strtolower($roleName).'@permissions.test'));
            $this->get(route('resources.index', ['resource' => 'users']))->assertOk()
                ->assertDontSee(route('resources.index', ['resource' => 'permissions']));
            foreach (['index', 'create', 'show', 'edit'] as $action) {
                $parameters = ['resource' => 'permissions'];
                if (in_array($action, ['show', 'edit'], true)) $parameters['record'] = $permission->id;
                $this->get(route('resources.'.$action, $parameters))->assertForbidden();
            }
            $this->post(route('resources.store', ['resource' => 'permissions']), ['name' => 'unsafe.create'])->assertForbidden();
            $this->put(route('resources.update', ['resource' => 'permissions', 'record' => $permission->id]), ['name' => 'unsafe.update'])->assertForbidden();
            $this->delete(route('resources.destroy', ['resource' => 'permissions', 'record' => $permission->id]))->assertForbidden();
            $this->getJson('/api/v1/permissions')->assertForbidden();
            $this->getJson('/api/v1/permissions/'.$permission->id)->assertForbidden();
            $this->postJson('/api/v1/permissions', ['name' => 'unsafe.create'])->assertForbidden();
            $this->patchJson('/api/v1/permissions/'.$permission->id, ['name' => 'unsafe.update'])->assertForbidden();
            $this->deleteJson('/api/v1/permissions/'.$permission->id)->assertForbidden();
        }
        $this->assertDatabaseCount('permissions', 1);
        $this->assertSame('users.manage', $permission->fresh()->name);
    }

    private function user(Role $role, string $email): User
    {
        return User::query()->create([
            'name' => 'Usuario',
            'email' => $email,
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
    }
}
