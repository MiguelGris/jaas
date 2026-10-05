<?php

namespace App\Support;

use App\Models\User;

final class ResourceAccess
{
    public static function isAdministrator(?User $user): bool
    {
        return in_array($user?->role?->name, ['ADMINISTRATOR', 'ADMINISTRADOR'], true);
    }

    public static function permission(string $resource, string $action = 'index'): string
    {
        return match ($resource) {
            'customers' => match ($action) {
                'create', 'store' => 'customers.create',
                'edit', 'update', 'destroy' => 'customers.update',
                default => 'customers.view',
            },
            'properties' => in_array($action, ['index', 'show'], true) ? 'customers.view' : 'customers.update',
            'connections' => in_array($action, ['create', 'store'], true) ? 'connections.create' : 'services.manage',
            'connection-usage-types', 'meters', 'meter-readings' => 'services.manage',
            'payments' => in_array($action, ['index', 'show'], true) ? 'reports.view' : 'payments.create',
            'rates', 'billing-periods', 'late-fee-settings' => 'rates.manage',
            'incomes', 'expenses', 'cash-closings' => 'cash.manage',
            'assemblies', 'assembly-attendances', 'fines' => 'assemblies.manage',
            'audit-logs' => 'audit.view',
            'roles', 'permissions', 'settings', 'users', 'customer-statuses', 'neighborhoods', 'connection-types', 'connection-statuses', 'usage-types', 'payment-methods', 'assembly-types', 'income-types', 'expense-categories' => 'users.manage',
            default => 'reports.view',
        };
    }

    public static function allows(?User $user, string $resource, string $action = 'index'): bool
    {
        return $user?->role !== null && (self::isAdministrator($user)
            || $user->role->permissions->contains('name', self::permission($resource, $action)));
    }
}
