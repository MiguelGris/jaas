<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->role !== null, 403);

        if ($user->role->name === 'ADMINISTRATOR') {
            return $next($request);
        }

        $permission ??= $this->permissionFor($request);

        abort_unless(
            $permission !== null && $user->role->permissions()->where('name', $permission)->exists(),
            403,
            'No tienes permiso para acceder a este módulo.'
        );

        return $next($request);
    }

    private function permissionFor(Request $request): ?string
    {
        $resource = $request->route('resource') ?? $this->resourceFromRouteName($request);

        return match ($resource) {
            'customers' => match ($request->method()) {
                'GET' => 'customers.view',
                'POST' => 'customers.create',
                default => 'customers.update',
            },
            'properties' => $request->isMethod('get') ? 'customers.view' : 'customers.update',
            'connections' => $request->isMethod('post') ? 'connections.create' : 'services.manage',
            'connection-usage-types', 'meters', 'meter-readings' => 'services.manage',
            'payments' => $request->isMethod('get') ? 'reports.view' : 'payments.create',
            'rates', 'billing-periods', 'late-fee-settings' => 'rates.manage',
            'incomes', 'expenses', 'cash-closings' => 'cash.manage',
            'assemblies', 'assembly-attendances', 'fines' => 'assemblies.manage',
            'audit-logs' => 'audit.view',
            'roles', 'permissions', 'settings', 'users', 'customer-statuses', 'neighborhoods', 'connection-types', 'connection-statuses', 'usage-types', 'payment-methods', 'assembly-types', 'income-types', 'expense-categories' => 'users.manage',
            default => 'reports.view',
        };
    }

    private function resourceFromRouteName(Request $request): ?string
    {
        $segments = explode('.', (string) $request->route()?->getName());

        return $segments[count($segments) - 2] ?? null;
    }
}
