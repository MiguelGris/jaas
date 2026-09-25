<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class JassAccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'services.manage' => 'Administrar conexiones, medidores y lecturas',
            'cash.manage' => 'Administrar ingresos, egresos y cierres de caja',
            'assemblies.manage' => 'Administrar asambleas, asistencias y multas',
        ] as $name => $description) {
            Permission::query()->updateOrCreate(['name' => $name], ['description' => $description]);
        }

        $permissions = Permission::query()->pluck('id', 'name');
        $assignments = [
            'ADMINISTRATOR' => $permissions->keys()->all(),
            'CASHIER' => ['customers.view', 'payments.create', 'reports.view'],
            'ACCOUNTING' => ['customers.view', 'rates.manage', 'cash.manage', 'reports.view'],
            'AUDITOR' => ['customers.view', 'reports.view', 'audit.view'],
            'OPERATOR' => ['customers.view', 'customers.create', 'customers.update', 'connections.create', 'services.manage', 'assemblies.manage', 'reports.view'],
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $role->permissions()->syncWithoutDetaching(
                $permissions->only($permissionNames)->values()->all(),
            );
        }

        $password = (string) env('JASS_DEFAULT_USER_PASSWORD', 'Cambiar123!');
        foreach ([
            ['role' => 'CASHIER', 'name' => 'Cajero', 'last_name' => 'JASS', 'email' => 'cajero@jass.local'],
            ['role' => 'ACCOUNTING', 'name' => 'Contabilidad', 'last_name' => 'JASS', 'email' => 'contabilidad@jass.local'],
            ['role' => 'AUDITOR', 'name' => 'Auditor', 'last_name' => 'JASS', 'email' => 'auditor@jass.local'],
            ['role' => 'OPERATOR', 'name' => 'Operador', 'last_name' => 'JASS', 'email' => 'operador@jass.local'],
        ] as $account) {
            User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'last_name' => $account['last_name'],
                    'password' => $password,
                    'role_id' => Role::query()->where('name', $account['role'])->value('id'),
                    'active' => true,
                ],
            );
        }
    }
}
