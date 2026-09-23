<?php

use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\AttendanceScannerController;
use App\Http\Controllers\Web\CollectionController;
use App\Http\Controllers\Web\JassPageController;
use App\Http\Controllers\Web\PublicDebtController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReportController;
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

Route::middleware(['auth', 'active', 'permission:reports.view'])->group(function (): void {
    Route::get('/panel', [JassPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/reportes/{report}/{format}', [ReportController::class, 'download'])->name('reports.download');
});

Route::middleware(['auth', 'active', 'permission:payments.create'])->prefix('cobranza')->name('collections.')->group(function (): void {
    Route::get('/', [CollectionController::class, 'create'])->name('create');
    Route::post('/', [CollectionController::class, 'store'])->name('store');
});

Route::get('/recibos/{payment}/imprimir', [ReceiptController::class, 'thermal'])
    ->middleware(['auth', 'active', 'permission:reports.view'])
    ->name('receipts.thermal');

Route::middleware(['auth', 'active', 'permission:reports.view'])->prefix('asistencias')->name('attendance.')->group(function (): void {
    Route::get('/lector', [AttendanceScannerController::class, 'create'])->name('scanner');
    Route::post('/lector', [AttendanceScannerController::class, 'store'])->name('scan');
});

Route::middleware(['auth', 'active', 'permission'])->prefix('gestion')->name('resources.')->group(function (): void {
    Route::get('{resource}', [JassPageController::class, 'index'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->name('index');
    Route::get('{resource}/create', [JassPageController::class, 'create'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->name('create');
    Route::post('{resource}', [JassPageController::class, 'store'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->name('store');
    Route::get('{resource}/{record}/edit', [JassPageController::class, 'edit'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->whereNumber('record')
        ->name('edit');
    Route::get('{resource}/{record}', [JassPageController::class, 'show'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->whereNumber('record')
        ->name('show');
    Route::put('{resource}/{record}', [JassPageController::class, 'update'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->whereNumber('record')
        ->name('update');
    Route::delete('{resource}/{record}', [JassPageController::class, 'destroy'])
        ->whereIn('resource', JassPageController::resourceSlugs())
        ->whereNumber('record')
        ->name('destroy');
});
