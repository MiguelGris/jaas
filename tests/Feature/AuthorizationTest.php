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
