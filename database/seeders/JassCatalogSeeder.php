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
            ['name' => 'ADMINISTRATOR', 'description' => 'Acceso completo al sistema'],
            ['name' => 'CASHIER', 'description' => 'Registro de pagos y cobranzas'],
            ['name' => 'ACCOUNTING', 'description' => 'Control financiero y reportes'],
            ['name' => 'AUDITOR', 'description' => 'Acceso de consulta y auditoría'],
            ['name' => 'OPERATOR', 'description' => 'Operaciones generales'],
        ], ['name'], ['description', 'updated_at'], $now);

        $this->upsert('permissions', [
            ['name' => 'customers.create', 'description' => 'Registrar clientes'],
            ['name' => 'customers.update', 'description' => 'Actualizar clientes'],
            ['name' => 'customers.view', 'description' => 'Consultar clientes'],
            ['name' => 'connections.create', 'description' => 'Registrar conexiones'],
            ['name' => 'payments.create', 'description' => 'Registrar pagos'],
            ['name' => 'rates.manage', 'description' => 'Administrar tarifas'],
            ['name' => 'reports.view', 'description' => 'Consultar reportes'],
            ['name' => 'users.manage', 'description' => 'Administrar usuarios'],
            ['name' => 'audit.view', 'description' => 'Consultar la bitácora de auditoría'],
        ], ['name'], ['description']);

        $this->upsert('settings', [
            ['key' => 'billing_period_months', 'value' => '3', 'description' => 'Meses agrupados en cada ciclo de pago; puede cambiarse a 6'],
            ['key' => 'payment_due_days', 'value' => '30', 'description' => 'Días permitidos para realizar el pago'],
        ], ['key'], ['value', 'description', 'updated_at'], $now);

        $this->upsert('customer_statuses', [
            ['name' => 'ACTIVO', 'description' => 'Cliente con servicio activo'],
            ['name' => 'INACTIVO', 'description' => 'Cliente sin servicio'],
            ['name' => 'EXONERADO', 'description' => 'Exonerado únicamente de multas por asamblea'],
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
            ['name' => 'AGUA', 'description' => 'Solo servicio de agua potable'],
            ['name' => 'DESAGÜE', 'description' => 'Solo servicio de desagüe'],
            ['name' => 'AGUA Y DESAGÜE', 'description' => 'Servicio combinado de agua y desagüe'],
        ], ['name'], ['description']);

        $this->upsert('connection_statuses', [
            ['name' => 'ACTIVO', 'description' => 'El servicio se encuentra operativo'],
            ['name' => 'SUSPENDIDO', 'description' => 'Servicio suspendido por deuda u otro motivo'],
            ['name' => 'INACTIVO', 'description' => 'Servicio retirado'],
        ], ['name'], ['description']);

        $this->upsert('usage_types', [
            ['name' => 'RESIDENCIAL', 'description' => 'Vivienda familiar'],
            ['name' => 'COMERCIAL', 'description' => 'Negocio o uso comercial'],
            ['name' => 'COMUNAL', 'description' => 'Local comunal'],
            ['name' => 'OTRO', 'description' => 'Otros usos'],
        ], ['name'], ['description']);

        foreach ([
            ['months' => 3, 'description' => 'Trimestral'],
            ['months' => 6, 'description' => 'Semestral'],
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
            ['name' => 'EFECTIVO'],
            ['name' => 'YAPE'],
            ['name' => 'PLIN'],
            ['name' => 'TRANSFERENCIA'],
        ], ['name'], ['name']);

        $this->upsert('assembly_types', [
            ['name' => 'Asamblea', 'description' => 'Asamblea para la toma de decisiones'],
            ['name' => 'Faena', 'description' => 'Actividad de trabajo comunal'],
        ], ['name'], ['description']);

        $this->upsert('income_types', [
            ['name' => 'CARGO POR SERVICIOS'],
            ['name' => 'MULTAS'],
            ['name' => 'DONACIÓN'],
            ['name' => 'OTRO'],
        ], ['name'], ['name']);

        $this->upsert('expense_categories', [
            ['name' => 'MANTENIMIENTO'],
            ['name' => 'MATERIALES'],
            ['name' => 'PERSONAL'],
            ['name' => 'SERVICIOS'],
            ['name' => 'OTRO'],
        ], ['name'], ['name']);

        $usageTypes = DB::table('usage_types')->pluck('id', 'name');

        foreach ([
            'RESIDENCIAL' => 15.00,
            'COMERCIAL' => 30.00,
            'COMUNAL' => 10.00,
            'OTRO' => 20.00,
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
