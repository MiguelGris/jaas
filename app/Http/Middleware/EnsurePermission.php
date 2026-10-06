<?php

namespace App\Http\Middleware;

use App\Support\ResourceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->role !== null, 403);

        // El administrador conserva acceso total aunque cambie el catálogo de
        // permisos; los demás roles deben tener el permiso exacto del módulo.
        if (ResourceAccess::isAdministrator($user)) {
            return $next($request);
        }

        if ($request->routeIs('resources.index') && $request->route('resource') === 'settings'
            && ResourceAccess::allows($user, 'late-fee-settings')) {
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
        // Las rutas genéricas indican el recurso como parámetro. Las rutas
        // especializadas (reportes, cobranza, etc.) se deducen por su nombre.
        $resource = $request->route('resource') ?? $this->resourceFromRouteName($request);

        $action = match ($request->method()) {
            'POST' => 'store', 'PUT', 'PATCH' => 'update', 'DELETE' => 'destroy',
            default => match (basename($request->path())) {
                'create', 'registrar' => 'create', 'edit', 'editar' => 'edit', default => 'index',
            },
        };

        return ResourceAccess::permission((string) $resource, $action);
    }

    private function resourceFromRouteName(Request $request): ?string
    {
        $segments = explode('.', (string) $request->route()?->getName());

        return $segments[count($segments) - 2] ?? null;
    }
}
