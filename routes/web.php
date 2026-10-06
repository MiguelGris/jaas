<?php

use App\Http\Controllers\Web\AttendanceScannerController;
use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\CollectionController;
use App\Http\Controllers\Web\DelinquencyController;
use App\Http\Controllers\Web\JassPageController;
use App\Http\Controllers\Web\PasswordController;
use App\Http\Controllers\Web\PublicDebtController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Middleware\SpanishWebRoutes;
use App\Services\SettingsListService;
use App\Support\SpanishRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDebtController::class, 'home'])->name('home');
Route::post('/consultar-deuda', [PublicDebtController::class, 'lookup'])
    ->middleware('throttle:10,1')
    ->name('debt.lookup');

Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/mi-cuenta/contrasena', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/mi-cuenta/contrasena', [PasswordController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware(['auth', 'active', SpanishWebRoutes::class, 'permission:reports.view'])->group(function (): void {
    Route::get('/panel', [JassPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/morosidad', [DelinquencyController::class, 'index'])->name('delinquencies.index');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/{report}/{format}', [ReportController::class, 'download'])->name('reports.download');
});

Route::middleware(['auth', 'active', 'permission:payments.create'])->prefix('cobranza')->name('collections.')->group(function (): void {
    Route::get('/', [CollectionController::class, 'create'])->name('create');
    Route::post('/', [CollectionController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'active', 'permission:rates.manage'])->group(function (): void {
    Route::get('/facturacion/generar', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/facturacion/generar', [BillingController::class, 'store'])->name('billing.store');
});

Route::get('/recibos/{payment}/imprimir', [ReceiptController::class, 'thermal'])
    ->middleware(['auth', 'active', 'permission:reports.view'])
    ->name('receipts.thermal');

Route::middleware(['auth', 'active', 'permission:payments.create'])->group(function (): void {
    Route::get('/recibos/{payment}/anular', [ReceiptController::class, 'annulForm'])->name('receipts.annul-form');
    Route::patch('/recibos/{payment}/anular', [ReceiptController::class, 'annul'])->name('receipts.annul');
});

Route::middleware(['auth', 'active', 'permission:assemblies.manage'])->prefix('asistencias')->name('attendance.')->group(function (): void {
    Route::get('/lector', [AttendanceScannerController::class, 'create'])->name('scanner');
    Route::post('/lector', [AttendanceScannerController::class, 'store'])->name('scan');
});

Route::middleware(['auth', 'active', SpanishWebRoutes::class, 'permission:rates.manage'])->group(function (): void {
    Route::get('/gestion/configuraciones/mora', function (JassPageController $controller, SettingsListService $settings) {
        $fee = $settings->currentMora();

        return $fee ? $controller->show('late-fee-settings', (string) $fee->id) : redirect()->route('settings.mora.edit');
    })->name('settings.mora.show');
    Route::get('/gestion/configuraciones/mora/editar', function (Request $request, JassPageController $controller, SettingsListService $settings) {
        $fee = $settings->currentMora();

        return $fee ? $controller->edit('late-fee-settings', (string) $fee->id) : $controller->create($request, 'late-fee-settings');
    })->name('settings.mora.edit');
    Route::get('/gestion/settings/mora', fn () => redirect()->route('settings.mora.show', [], 301));
    Route::get('/gestion/settings/mora/editar', fn () => redirect()->route('settings.mora.edit', [], 301));
});

Route::middleware(['auth', 'active', SpanishWebRoutes::class, 'permission'])->prefix('gestion')->name('resources.')->group(function (): void {
    Route::get('{resource}', [JassPageController::class, 'index'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->name('index');
    Route::get('{resource}/registrar', [JassPageController::class, 'create'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->name('create');
    Route::post('{resource}', [JassPageController::class, 'store'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->name('store');
    Route::get('{resource}/{record}/editar', [JassPageController::class, 'edit'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->whereNumber('record')
        ->name('edit');
    Route::get('{resource}/{record}', [JassPageController::class, 'show'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->whereNumber('record')
        ->name('show');
    Route::put('{resource}/{record}', [JassPageController::class, 'update'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->whereNumber('record')
        ->name('update');
    Route::delete('{resource}/{record}', [JassPageController::class, 'destroy'])
        ->whereIn('resource', array_unique([...JassPageController::resourceSlugs(), ...array_values(SpanishRoutes::RESOURCES)]))
        ->whereNumber('record')
        ->name('destroy');
    Route::get('{resource}/create', [JassPageController::class, 'create'])
        ->whereIn('resource', array_keys(SpanishRoutes::RESOURCES))->name('legacy-create');
    Route::get('{resource}/{record}/edit', [JassPageController::class, 'edit'])
        ->whereIn('resource', array_keys(SpanishRoutes::RESOURCES))->whereNumber('record')->name('legacy-edit');
});
