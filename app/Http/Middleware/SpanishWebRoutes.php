<?php

namespace App\Http\Middleware;

use App\Support\SpanishRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SpanishWebRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['resource', 'report'] as $parameter) {
            if ($value = $request->route($parameter)) {
                $request->route()->setParameter($parameter, SpanishRoutes::$parameter($value));
            }
        }
        $path = '/'.$request->path();
        $canonical = SpanishRoutes::path($path);
        if ($request->isMethod('GET') && $path !== $canonical) {
            return redirect($canonical.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        return $next($request);
    }
}
