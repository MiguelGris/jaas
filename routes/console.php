<?php

use App\Services\BillingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:generate-monthly')
    ->cron('5 */3 * * *')
    ->timezone('America/Lima')
    ->when(fn (): bool => now('America/Lima')->day === app(BillingService::class)->issueDay())
    ->withoutOverlapping();
