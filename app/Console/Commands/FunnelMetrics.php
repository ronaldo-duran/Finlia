<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReceivableStatus;
use App\Models\Household;
use App\Models\Receivable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del embudo (plan de lanzamiento, T5). Solo lectura, sin tablas nuevas.
 *
 * El Scheduler lo corre cada semana y manda la salida al buzón de contacto. La
 * cifra que dice si la app sirve —y no solo si la probaron— es la de gastos en
 * dos días distintos.
 */
class FunnelMetrics extends Command
{
    protected $signature = 'finlia:metrics';

    protected $description = 'Muestra las métricas del embudo: registro, verificación, uso y retención';

    public function handle(): int
    {
        $reales = fn () => User::query()->where('email', 'not like', 'deleted+%');

        $registrados = $reales()->count();
        $verificados = $reales()->whereNotNull('email_verified_at')->count();

        $hogaresConMovimientos = Household::query()
            ->where(fn ($q) => $q->has('expenses')->orHas('incomes'))
            ->count();

        $fecha = DB::connection()->getQueryGrammar()->wrap('date');
        $usoRepetido = DB::query()->fromSub(
            DB::table('expenses')
                ->whereNull('deleted_at')
                ->whereIn('user_id', $reales()->select('id'))
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw("COUNT(DISTINCT {$fecha}) >= 2"),
            'uso',
        )->count();

        $ultimoGasto = DB::table('expenses')
            ->whereNull('deleted_at')
            ->selectRaw('user_id, MAX(created_at) AS ultimo')
            ->groupBy('user_id')
            ->pluck('ultimo', 'user_id');

        $cohorte = $reales()->where('created_at', '<=', now()->subDays(7))->get(['id', 'created_at']);

        $activos = $cohorte->filter(fn (User $user) => isset($ultimoGasto[$user->id])
            && CarbonImmutable::parse($ultimoGasto[$user->id])->gte($user->created_at->copy()->addDays(7)),
        )->count();

        $receivablesOpen = Receivable::whereIn('status', ReceivableStatus::outstandingValues())
            ->where('current_balance', '>', 0);
        $totalPorCobrar = (float) (clone $receivablesOpen)->sum('current_balance');

        $this->table(['Métrica', 'Valor'], [
            ['Registros', $registrados],
            ['Verificados', $verificados.' ('.$this->porcentaje($verificados, $registrados).')'],
            ['Hogares con al menos un movimiento', $hogaresConMovimientos],
            ['Usuarios con gastos en 2 días distintos', $usoRepetido],
            ['Activos 7 días después de registrarse', $activos.' de '.$cohorte->count()],
            ['Eliminaciones pedidas (en suspensión)', $reales()->whereNotNull('deletion_requested_at')->count()],
            ['Cuentas ya eliminadas', User::query()->where('email', 'like', 'deleted+%')->count()],
            ['Cuentas por cobrar con saldo > 0', $receivablesOpen->count()],
            ['Total por cobrar (todos los hogares)', number_format($totalPorCobrar, 2, ',', '.')],
        ]);

        return self::SUCCESS;
    }

    private function porcentaje(int $parte, int $total): string
    {
        return $total === 0 ? '—' : round(100 * $parte / $total).' %';
    }
}
