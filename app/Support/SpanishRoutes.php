<?php

namespace App\Support;

final class SpanishRoutes
{
    public const RESOURCES = [
        'customers' => 'clientes', 'properties' => 'predios', 'connections' => 'conexiones',
        'connection-usage-types' => 'asignaciones-de-uso', 'meters' => 'medidores', 'meter-readings' => 'lecturas',
        'rates' => 'tarifas', 'billing-periods' => 'ciclos-de-pago', 'late-fee-settings' => 'configuracion-de-mora',
        'invoices' => 'cuotas', 'payments' => 'recibos-de-pago', 'assemblies' => 'asambleas',
        'assembly-attendances' => 'asistencias', 'fines' => 'multas', 'incomes' => 'ingresos', 'expenses' => 'egresos',
        'cash-closings' => 'cierres-de-caja', 'customer-statuses' => 'estados-de-cliente', 'neighborhoods' => 'sectores',
        'connection-types' => 'tipos-de-conexion', 'connection-statuses' => 'estados-de-conexion',
        'usage-types' => 'tipos-de-uso', 'payment-methods' => 'metodos-de-pago', 'assembly-types' => 'tipos-de-asamblea',
        'income-types' => 'tipos-de-ingreso', 'expense-categories' => 'categorias-de-egreso',
        'users' => 'usuarios', 'roles' => 'roles', 'permissions' => 'permisos', 'settings' => 'configuraciones',
        'audit-logs' => 'bitacora',
    ];

    public const REPORTS = [
        'cash-flow' => 'flujo-de-caja', 'annual-balance' => 'balance-anual',
        'payment-concepts-monthly' => 'conceptos-de-pago-mensuales', 'payment-concepts-annual' => 'conceptos-de-pago-anuales',
        'customer-payment-history' => 'historial-de-pagos', 'debtors' => 'morosos', 'debt-aging' => 'antiguedad-de-deuda',
        'payment-methods' => 'recaudacion-por-medio-de-pago', 'service-register' => 'padron-de-conexiones',
        'attendance' => 'asistencias', 'work-exemptions' => 'exonerados-de-faenas',
    ];

    public static function path(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'gestion') {
            $segments[1] = self::RESOURCES[$segments[1] ?? ''] ?? ($segments[1] ?? '');
            $last = count($segments) - 1;
            if ($last >= 2) {
                $segments[$last] = ['create' => 'registrar', 'edit' => 'editar'][$segments[$last]] ?? $segments[$last];
            }
        } elseif (($segments[0] ?? '') === 'reportes' && isset($segments[1])) {
            $segments[1] = self::REPORTS[$segments[1]] ?? $segments[1];
        }

        return '/'.implode('/', $segments);
    }

    public static function resource(string $slug): string
    {
        return array_flip(self::RESOURCES)[$slug] ?? $slug;
    }

    public static function report(string $slug): string
    {
        return array_flip(self::REPORTS)[$slug] ?? $slug;
    }
}
