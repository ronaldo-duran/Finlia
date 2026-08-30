<?php

namespace App\Providers;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Household;
use App\Models\RecurringExpense;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Observers\ReminderSummaryCacheObserver;
use App\Services\ReminderService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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
    public function boot(ReminderService $reminders): void
    {
        // Reenvío del correo de verificación (Plan 01): ~3/minuto POR
        // USUARIO, no por IP (mismo usuario, otro navegador). El enlace
        // firmado público usa el throttle numérico estándar en la ruta.
        RateLimiter::for('verification', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Directiva @money($monto): formato COP centralizado (ADR-0006).
        Blade::directive('money', function (string $expression): string {
            return "<?php echo money($expression); ?>";
        });

        // Invalidación del resumen cacheado de recordatorios: cualquier
        // mutación de una fuente (recurrente, deuda/pago, meta/aporte,
        // aviso suelto o el propio hogar) borra la clave de ese hogar.
        foreach ([
            Debt::class,
            DebtPayment::class,
            Household::class,
            RecurringExpense::class,
            Reminder::class,
            SavingsGoal::class,
            SavingsGoalContribution::class,
        ] as $model) {
            $model::observe(ReminderSummaryCacheObserver::class);
        }

        // Directiva @percent($valor): "80 %", "332,4 %" (coma decimal, Épica 4).
        Blade::directive('percent', function (string $expression): string {
            return "<?php echo percent($expression); ?>";
        });

        // Campanita de recordatorios (Épica 9): vive en el navbar de TODAS
        // las páginas autenticadas, así que se alimenta por view composer
        // con el resumen CACHEADO (cachedSummary) — así la campanita no
        // cuesta una query por página. Si el hogar los desactivó, el conteo
        // llega en cero (la campana ni se enciende).
        View::composer('layouts.partials.reminders-bell', function (ViewContract $view) use ($reminders): void {
            $household = auth()->check() ? active_household() : null;

            if ($household === null) {
                $view->with('bellSummary', null);

                return;
            }

            $enabled = (bool) $household->reminders_enabled;

            $view->with('bellSummary', [
                'enabled' => $enabled,
                ...($enabled
                    ? $reminders->cachedSummary($household->id)
                    : ['overdue' => 0, 'upcoming' => 0, 'attention' => 0, 'total' => 0]),
            ]);
        });
    }
}
