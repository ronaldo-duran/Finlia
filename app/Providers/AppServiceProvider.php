<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        // Directiva @money($monto): formato COP centralizado (ADR-0006).
        Blade::directive('money', function (string $expression): string {
            return "<?php echo money($expression); ?>";
        });
    }
}
