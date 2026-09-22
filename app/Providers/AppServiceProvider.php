<?php

namespace App\Providers;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Household;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\RecurringExpense;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Observers\ReminderSummaryCacheObserver;
use App\Services\ReminderService;
use Finlia\Ee\EeServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UncompromisedVerifier::class,
            fn ($app) => new NotPwnedVerifier(
                $app[Factory::class],
                timeout: 3,
            ),
        );

        if (class_exists(EeServiceProvider::class)) {
            $this->app->register(EeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(ReminderService $reminders): void
    {
        RateLimiter::for('verification', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        PasswordRule::defaults(function (): PasswordRule {
            $rule = PasswordRule::min(8)->max(72);

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        Blade::directive('money', function (string $expression): string {
            return "<?php echo money($expression); ?>";
        });

        foreach ([
            Debt::class,
            DebtPayment::class,
            Household::class,
            Receivable::class,
            ReceivablePayment::class,
            RecurringExpense::class,
            Reminder::class,
            SavingsGoal::class,
            SavingsGoalContribution::class,
        ] as $model) {
            $model::observe(ReminderSummaryCacheObserver::class);
        }

        Blade::directive('percent', function (string $expression): string {
            return "<?php echo percent($expression); ?>";
        });

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
