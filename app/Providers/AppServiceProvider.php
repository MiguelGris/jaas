<?php

namespace App\Providers;

use App\Http\Controllers\Web\JassPageController;
use App\Support\SpanishRoutes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', fn ($view) => $view->with('navigation', JassPageController::navigation()));
        App::setLocale('es');
        App::setFallbackLocale('es');
        Carbon::setLocale('es');
        URL::formatPathUsing(fn ($path, $route) => $route && (str_starts_with($route->getName() ?? '', 'resources.')
                || str_starts_with($route->getName() ?? '', 'settings.mora.')
                || $route->getName() === 'reports.download')
                ? SpanishRoutes::path($path) : $path
        );
    }
}
