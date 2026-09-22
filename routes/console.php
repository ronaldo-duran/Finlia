<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('finlia:generate-recurring-payments')
    ->name('recurrentes-auto-generacion')
    ->withoutOverlapping()
    ->dailyAt('06:00');

Schedule::command('finlia:send-reminder-digests')
    ->name('recordatorios-digest')
    ->withoutOverlapping()
    ->dailyAt('06:30');

Schedule::command('finlia:purge-pending-deletions')
    ->name('purga-cuentas')
    ->withoutOverlapping()
    ->dailyAt('05:30');

Schedule::command('finlia:process-export-requests')
    ->name('exportaciones-datos')
    ->withoutOverlapping()
    ->dailyAt('02:00');

Schedule::command('finlia:report-errors')
    ->name('aviso-errores')
    ->withoutOverlapping()
    ->hourly();

if (config('finlia.contact.inbox') && mail_is_deliverable()) {
    Schedule::command('finlia:metrics')
        ->name('metricas-embudo')
        ->weeklyOn(1, '07:00')
        ->emailOutputTo(config('finlia.contact.inbox'));
}
