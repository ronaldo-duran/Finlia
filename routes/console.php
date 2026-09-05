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
// antes de que el hogar empiece el día (America/Bogota). withoutOverlapping:
// si una corrida se alarga, el cron del minuto siguiente no lanza otra encima.
Schedule::command('finlia:generate-recurring-payments')
    ->name('recurrentes-auto-generacion')
    ->withoutOverlapping()
    ->dailyAt('06:00');

// Épica 9 (ADR-0028): digest de recordatorios urgentes por correo. Después
// de la generación de recurrentes, para que refleje los pagos de la mañana.
// Síncrono a propósito (Fase 1); la corrida puede durar minutos con volumen,
// así que tampoco puede solaparse con la del minuto siguiente.
Schedule::command('finlia:send-reminder-digests')
    ->name('recordatorios-digest')
    ->withoutOverlapping()
    ->dailyAt('06:30');

// Plan 05 (ADR-0033): purga cuentas suspendidas con ventana expirada (> 30 días)
// y cuentas fantasma sin verificar (> 14 días). Antes que los recurrentes para
// que un usuario purgado no genere pagos de la mañana.
Schedule::command('finlia:purge-pending-deletions')
    ->name('purga-cuentas')
    ->withoutOverlapping()
    ->dailyAt('05:30');
