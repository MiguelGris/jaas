<?php

use App\Http\Controllers\Api\JassResourceController;
use Illuminate\Support\Facades\Route;

/*
 * Internal API surface for the JASS application. It shares the authenticated
 * web session until a token guard (such as Sanctum) is introduced.
 */
Route::middleware(['web', 'auth', 'active', 'permission'])->prefix('v1')->name('v1.')->group(function (): void {
    foreach (JassResourceController::resourceNames() as $resource) {
        $route = Route::apiResource($resource, JassResourceController::class);

        if ($resource === 'audit-logs') {
            $route->only(['index', 'show']);
        }
    }
});
