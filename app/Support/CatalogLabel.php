<?php

namespace App\Support;

final class CatalogLabel
{
    public static function role(?string $role): string
    {
        return match ($role) {
            'ADMINISTRATOR' => 'Administrador',
            'CASHIER' => 'Caja',
            'ACCOUNTING' => 'Contabilidad',
            'AUDITOR' => 'Auditoría',
            'OPERATOR' => 'Operador',
            null, '' => 'Sin rol',
            default => str($role)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    public static function value(mixed $value): string
    {
        if (! is_string($value)) {
            return (string) $value;
        }

        return match ($value) {
            'ACTIVE' => 'Activo',
            'INACTIVE' => 'Inactivo',
            'EXEMPT' => 'Exonerado',
            'SUSPENDED' => 'Suspendido',
            'WATER' => 'Agua',
            'SEWER' => 'Desagüe',
            'WATER_AND_SEWER' => 'Agua y desagüe',
            'FIXED' => 'Pago fijo',
            'METERED' => 'Con medidor',
            'RESIDENTIAL' => 'Residencial',
            'COMMERCIAL' => 'Comercial',
            'COMMUNITY' => 'Comunitario',
            'OTHER' => 'Otro',
            'QUARTERLY' => 'Trimestral',
            'SEMIANNUAL' => 'Semestral',
            'CASH' => 'Efectivo',
            'YAPE' => 'Yape',
            'PLIN' => 'Plin',
            'TRANSFER' => 'Transferencia',
            'MEETING' => 'Asamblea',
            'COMMUNITY_WORK' => 'Faena comunal',
            'SERVICE_FEE' => 'Cuota de servicio',
            'FINE' => 'Multa',
            'DONATION' => 'Donación',
            'MAINTENANCE' => 'Mantenimiento',
            'MATERIALS' => 'Materiales',
            'PERSONNEL' => 'Personal',
            'SERVICES' => 'Servicios',
            'billing_period_months' => 'Meses del ciclo de pago',
            'payment_due_days' => 'Días permitidos para el pago',
            'billing_issue_day' => 'Día de emisión de cuotas',
            'work_exemption_age' => 'Edad de exoneración de faenas',
            'ADMINISTRATOR', 'CASHIER', 'ACCOUNTING', 'AUDITOR', 'OPERATOR' => self::role($value),
            default => $value,
        };
    }
}
