<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JassCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $this->upsert('roles', [
            ['name' => 'ADMINISTRATOR', 'description' => 'Full system access'],
            ['name' => 'CASHIER', 'description' => 'Payment and collection records'],
            ['name' => 'ACCOUNTING', 'description' => 'Financial control and reports'],
            ['name' => 'AUDITOR', 'description' => 'Read-only review access'],
            ['name' => 'OPERATOR', 'description' => 'General operations'],
        ], ['name'], ['description', 'updated_at'], $now);

        $this->upsert('permissions', [
            ['name' => 'customers.create', 'description' => 'Register customers'],
            ['name' => 'customers.update', 'description' => 'Update customers'],
            ['name' => 'customers.view', 'description' => 'View customers'],
            ['name' => 'connections.create', 'description' => 'Register connections'],
            ['name' => 'payments.create', 'description' => 'Register payments'],
            ['name' => 'rates.manage', 'description' => 'Manage rates'],
            ['name' => 'reports.view', 'description' => 'View reports'],
            ['name' => 'users.manage', 'description' => 'Manage users'],
            ['name' => 'audit.view', 'description' => 'View audit logs'],
        ], ['name'], ['description']);

        $this->upsert('settings', [
            ['key' => 'billing_period_months', 'value' => '3', 'description' => 'Months billed together; it can be changed to 6'],
            ['key' => 'payment_due_days', 'value' => '30', 'description' => 'Days allowed for payment'],
        ], ['key'], ['value', 'description', 'updated_at'], $now);

        $this->upsert('customer_statuses', [
            ['name' => 'ACTIVE', 'description' => 'Customer with an active service'],
            ['name' => 'INACTIVE', 'description' => 'Customer without service'],
            ['name' => 'EXEMPT', 'description' => 'Exempt from assembly fines only'],
        ], ['name'], ['description']);

        $this->upsert('neighborhoods', [
            ['name' => 'Mariscal Caceres', 'description' => 'Barrio zona centro-sur'],
            ['name' => 'San Pedro', 'description' => 'Barrio zona centro-oeste'],
            ['name' => '28 de Julio', 'description' => 'Barrio zona este'],
            ['name' => 'Progreso', 'description' => 'Barrio zona oeste'],
            ['name' => 'San Lorenzo', 'description' => 'Barrio zona nor-oeste'],
            ['name' => 'La Breña', 'description' => 'Barrio zona sur'],
            ['name' => 'Cesar Vallejo', 'description' => 'Barrio zona norte'],
        ], ['name'], ['description', 'updated_at'], $now);

        $this->upsert('connection_types', [
            ['name' => 'WATER', 'description' => 'Potable water service only'],
            ['name' => 'SEWER', 'description' => 'Sewer service only'],
            ['name' => 'WATER_AND_SEWER', 'description' => 'Combined service'],
        ], ['name'], ['description']);

        $this->upsert('connection_statuses', [
            ['name' => 'ACTIVE', 'description' => 'Service is operational'],
            ['name' => 'SUSPENDED', 'description' => 'Service suspended due to debt or another reason'],
            ['name' => 'INACTIVE', 'description' => 'Service removed'],
        ], ['name'], ['description']);

        $this->upsert('usage_types', [
            ['name' => 'RESIDENTIAL', 'description' => 'Family residence'],
            ['name' => 'COMMERCIAL', 'description' => 'Business or commercial use'],
            ['name' => 'COMMUNITY', 'description' => 'Community premises'],
            ['name' => 'OTHER', 'description' => 'Other uses'],
        ], ['name'], ['description']);

        foreach ([
            ['months' => 3, 'description' => 'QUARTERLY'],
            ['months' => 6, 'description' => 'SEMIANNUAL'],
        ] as $billingPeriod) {
            DB::table('billing_periods')->updateOrInsert(
                ['months' => $billingPeriod['months']],
                ['description' => $billingPeriod['description']]
            );
        }

        DB::table('late_fee_settings')->updateOrInsert(
            ['starts_on' => '2026-01-01'],
            ['monthly_amount' => 2.00, 'grace_days' => 30, 'ends_on' => null]
        );

        $this->upsert('payment_methods', [
            ['name' => 'CASH'],
            ['name' => 'YAPE'],
            ['name' => 'PLIN'],
            ['name' => 'TRANSFER'],
        ], ['name'], ['name']);

        $this->upsert('assembly_types', [
            ['name' => 'MEETING', 'description' => 'Decision-making meeting'],
            ['name' => 'COMMUNITY_WORK', 'description' => 'Community work activity'],
        ], ['name'], ['description']);

        $this->upsert('income_types', [
            ['name' => 'SERVICE_FEE'],
            ['name' => 'FINE'],
            ['name' => 'DONATION'],
            ['name' => 'OTHER'],
        ], ['name'], ['name']);

        $this->upsert('expense_categories', [
            ['name' => 'MAINTENANCE'],
            ['name' => 'MATERIALS'],
            ['name' => 'PERSONNEL'],
            ['name' => 'SERVICES'],
            ['name' => 'OTHER'],
        ], ['name'], ['name']);

        $usageTypes = DB::table('usage_types')->pluck('id', 'name');

        foreach ([
            'RESIDENTIAL' => 15.00,
            'COMMERCIAL' => 30.00,
            'COMMUNITY' => 10.00,
            'OTHER' => 20.00,
        ] as $usageType => $amount) {
            DB::table('rates')->updateOrInsert(
                ['usage_type_id' => $usageTypes[$usageType], 'year' => 2026],
                ['amount' => $amount, 'starts_on' => '2026-01-01', 'ends_on' => null, 'approved_by_assembly' => false]
            );
        }

        User::query()->firstOrCreate(
            ['email' => 'admin@jass.local'],
            [
                'name' => 'Administrador',
                'last_name' => 'JASS',
                'password' => Hash::make((string) env('JASS_ADMIN_PASSWORD', 'Cambiar123!')),
                'role_id' => DB::table('roles')->where('name', 'ADMINISTRATOR')->value('id'),
                'active' => true,
            ]
        );
    }

    private function upsert(string $table, array $rows, array $uniqueBy, array $update, $now = null): void
    {
        if ($now !== null) {
            $rows = array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $rows);
        }

        DB::table($table)->upsert($rows, $uniqueBy, $update);
    }
}
