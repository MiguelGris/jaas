<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $incidentId = static function (): string {
            $request = request();
            if (! $request->attributes->has('error_incident')) {
                $request->attributes->set('error_incident', (string) Str::uuid());
            }

            return $request->attributes->get('error_incident');
        };
        $exceptions->context(fn () => ['incident' => $incidentId()]);
        $exceptions->respond(function ($response) use ($incidentId) {
            if ($response->getStatusCode() < 500) {
                return $response;
            }
            $incident = $incidentId();
            Log::error('Respuesta de error del servidor', [
                'incident' => $incident, 'path' => request()->path(), 'status' => $response->getStatusCode(),
            ]);
            $message = 'No pudimos completar la operación. Vuelve al inicio y revisa si se guardó antes de intentarlo de nuevo.';

            return request()->is('api/*')
                ? response()->json(['message' => $message, 'incident' => $incident], $response->getStatusCode())
                : response()->view('errors.500', compact('incident'), $response->getStatusCode());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
