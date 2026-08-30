<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas — Finlia
|--------------------------------------------------------------------------
|
| Compatible con hosting compartido (ADR de despliegue): el cron de
| Hostinger llama a `php artisan schedule:run` cada minuto y Laravel
| decide qué corre. Nada de workers persistentes (ver docs/DEPLOYMENT.md §6).
|
*/

// Épica 9 (ADR-0018): pagos automáticos de recurrentes vencidos. Temprano,
// antes de que el hogar empiece el día (America/Bogota).
Schedule::command('finlia:generate-recurring-payments')
    ->name('recurrentes-auto-generacion')
    ->dailyAt('06:00');
