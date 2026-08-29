<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SavingsGoalPriority;
use App\Enums\SavingsGoalStatus;
use App\Models\Household;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de metas de ahorro (Épica 7): aportes/retiros, progreso, aporte
 * mensual recomendado, fondo de emergencia y el término `savings` que
 * alimenta el dinero disponible (ADR-0014).
 *
 * Seam (ADR-0010): no depende de la capa HTTP. Recibe IDs, modelos y fechas
 * explícitos y devuelve arrays/colecciones serializables, así que la futura
 * API (Épica 14) reutiliza lo mismo sin reescribir.
 */
class SavingsGoalService
{
    // ---------------------------------------------------------------
    // Altas y edición
    // ---------------------------------------------------------------

    /**
     * Crea una meta. El ahorrado arranca en cero, no lo teclea el usuario
     * (ADR-0025): lo que ya se tenía se registra como aporte inicial.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGoal(Household $household, array $data): SavingsGoal
    {
        $goal = $household->savingsGoals()->make($data);
        $goal->current_amount = 0;
        $goal->save();

        return $goal;
    }

    /**
     * Actualiza los datos editables. El ahorrado NO se toca: sale de los
     * movimientos registrados (ADR-0025), así que editar no lo altera.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGoal(SavingsGoal $goal, array $data): SavingsGoal
    {
        $goal->fill($data);
        $goal->save();

        return $goal;
    }

    // ---------------------------------------------------------------
    // Aportes y retiros
    // ---------------------------------------------------------------

    /**
     * Registra un aporte o retiro y recalcula lo ahorrado, todo en una
     * transacción: o quedan las dos cosas o ninguna.
     *
     * NO mueve cuentas ni crea gastos (ADR-0025): ahorrar no es gastar; la
     * transferencia entre cuentas quedó diferida a la Épica 10.
     *
     * @param  array<string, mixed>  $data  amount, date, type, notes?
     */
    public function registerContribution(SavingsGoal $goal, array $data): SavingsGoalContribution
    {
        return DB::transaction(function () use ($goal, $data): SavingsGoalContribution {
            $contribution = $goal->contributions()->make([
                'amount' => $data['amount'],
                'date' => $data['date'],
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
            ]);
            $contribution->household_id = $goal->household_id;
            $contribution->save();

            $this->recalculateAmount($goal);

            return $contribution;
        });
    }

    /**
     * Borra un movimiento y deshace su efecto: recalcula lo ahorrado y el
     * estado (borrar un aporte puede reabrir una meta lograda).
     */
    public function deleteContribution(SavingsGoalContribution $contribution): void
    {
        DB::transaction(function () use ($contribution): void {
            $goal = $contribution->goal;

            $contribution->delete();

            $this->recalculateAmount($goal);
        });
    }

    /**
     * Recalcula y persiste lo ahorrado: Σ aportes − Σ retiros. La fuente de
     * verdad es el historial, no un número tecleado (ADR-0025).
     *
     * Al alcanzar el objetivo la meta se marca lograda; si vuelve a quedar
     * por debajo (se borró un aporte) regresa a activa. Espejo del toggle
     * activa↔pagada de las deudas (ADR-0020).
     */
    public function recalculateAmount(SavingsGoal $goal): SavingsGoal
    {
        $saved = (float) $goal->contributions()
            ->selectRaw("CASE type WHEN 'deposit' THEN amount ELSE -amount END as signed_amount")
            ->get()
            ->sum('signed_amount');

        $goal->current_amount = round(max(0.0, $saved), 2);

        // El estado solo se mueve entre "activa" y "lograda": pausada y
        // archivada mantienen su estado, que es información del usuario.
        if ((float) $goal->current_amount >= (float) $goal->target_amount
            && $goal->status === SavingsGoalStatus::Active) {
            $goal->status = SavingsGoalStatus::Completed;
        } elseif ((float) $goal->current_amount < (float) $goal->target_amount
            && $goal->status === SavingsGoalStatus::Completed) {
            $goal->status = SavingsGoalStatus::Active;
        }

        $goal->save();

        return $goal;
    }

    // ---------------------------------------------------------------
    // Estados
    // ---------------------------------------------------------------

    /**
     * Pausa una meta activa: deja de comprometer su aporte mensual en el
     * presupuesto. El dinero ya ahorrado sigue ahí.
     */
    public function pause(SavingsGoal $goal): SavingsGoal
    {
        $goal->status = SavingsGoalStatus::Paused;
        $goal->save();

        return $goal;
    }

    /** Reactiva una meta pausada. */
    public function resume(SavingsGoal $goal): SavingsGoal
    {
        $goal->status = SavingsGoalStatus::Active;
        $goal->save();

        return $goal;
    }

    /**
     * Marca la meta como lograda. Manual: a veces se cumple con menos de lo
     * planeado (salió más barato) y el usuario decide cerrarla igual.
     */
    public function complete(SavingsGoal $goal): SavingsGoal
    {
        $goal->status = SavingsGoalStatus::Completed;
        $goal->save();

        return $goal;
    }

    /**
     * Archiva la meta: deja de aparecer en el panel vigente sin perder su
     * historial. Es un "olvídala" reversible.
     */
    public function archive(SavingsGoal $goal): SavingsGoal
    {
        $goal->status = SavingsGoalStatus::Archived;
        $goal->save();

        return $goal;
    }

    // ---------------------------------------------------------------
    // Cálculos
    // ---------------------------------------------------------------

    /**
     * Aporte mensual recomendado (estimación, la UI lo dice): lo que falta
     * repartido en los meses que quedan hasta la fecha objetivo.
     *
     * Sin fecha objetivo o con fecha vencida no hay nada que recomendar
     * (null): la UI muestra la meta como abierta o vencida.
     *
     * @return array{amount: ?float, months: ?int, possible: bool}
     */
    public function recommendedMonthlyContribution(SavingsGoal $goal, ?CarbonInterface $reference = null): array
    {
        $remaining = $goal->remainingAmount();

        if ($remaining <= 0.0) {
            return ['amount' => 0.0, 'months' => 0, 'possible' => true];
        }

        $today = $reference !== null
            ? Carbon::parse($reference)->startOfDay()
            : Carbon::now(config('app.timezone'))->startOfDay();

        $targetDate = $goal->target_date;

        if ($targetDate === null || $targetDate->isPast()) {
            return ['amount' => null, 'months' => null, 'possible' => false];
        }

        // Ceil: medio mes cuenta como uno (falta 15 días → 1 mes).
        $months = max(1, (int) ceil($today->diffInMonths($targetDate)));

        return [
            'amount' => round($remaining / $months, 2),
            'months' => $months,
            'possible' => true,
        ];
    }

    // ---------------------------------------------------------------
    // Panel y seam del dinero disponible (ADR-0014)
    // ---------------------------------------------------------------

    /**
     * Resumen del hogar para el panel: total ahorrado, objetivo total,
     * progreso y compromiso mensual.
     *
     * @return array<string, mixed>
     */
    public function summary(int $householdId): array
    {
        /** @var Collection<int, SavingsGoal> $goals */
        $goals = SavingsGoal::where('household_id', $householdId)->outstanding()->get();

        $totalTarget = round((float) $goals->sum(fn (SavingsGoal $g) => (float) $g->target_amount), 2);
        $totalSaved = round((float) $goals->sum(fn (SavingsGoal $g) => (float) $g->current_amount), 2);

        return [
            'count' => $goals->count(),
            'total_target' => $totalTarget,
            'total_saved' => $totalSaved,
            'total_remaining' => round(max(0.0, $totalTarget - $totalSaved), 2),
            'monthly_commitment' => $this->committedMonthly($householdId),
            'progress_percent' => $totalTarget > 0.0
                ? round(min(100.0, $totalSaved / $totalTarget * 100), 1)
                : 0.0,
            'overdue_count' => $goals->filter(fn (SavingsGoal $g) => $g->isOverdue())->count(),
        ];
    }

    /**
     * Aporte mensual COMPROMETIDO del hogar (seam `savings`, ADR-0014): la
     * suma de lo que las metas activas destinan cada mes a ahorrar.
     *
     * Solo metas ACTIVAS: pausar una meta es exactamente dejar de comprometer
     * ese dinero. Cada meta cuenta lo que le falte, no más (la última cuota
     * nunca supera el faltante). Pausada, lograda y archivada no cuentan.
     */
    public function committedMonthly(int $householdId): float
    {
        $committed = SavingsGoal::where('household_id', $householdId)
            ->where('status', SavingsGoalStatus::Active->value)
            ->whereNotNull('monthly_commitment')
            ->get()
            ->sum(fn (SavingsGoal $g) => min(
                (float) $g->monthly_commitment,
                $g->remainingAmount(),
            ));

        return round((float) $committed, 2);
    }

    /**
     * Metas vigentes ordenadas para el dashboard: prioridad alta primero y,
     * a igual prioridad, la más urgente por fecha objetivo (sin fecha al final).
     *
     * @return Collection<int, SavingsGoal>
     */
    public function outstandingGoals(int $householdId): Collection
    {
        $rank = [
            SavingsGoalPriority::High->value => 0,
            SavingsGoalPriority::Medium->value => 1,
            SavingsGoalPriority::Low->value => 2,
            null => 3,
        ];

        return SavingsGoal::where('household_id', $householdId)
            ->outstanding()
            ->get()
            ->sort(function (SavingsGoal $a, SavingsGoal $b) use ($rank): int {
                $pa = $rank[$a->priority?->value ?? null] ?? 3;
                $pb = $rank[$b->priority?->value ?? null] ?? 3;

                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                // A igual prioridad, la fecha más próxima primero; sin fecha
                // al final.
                $da = $a->target_date?->getTimestamp() ?? PHP_INT_MAX;
                $db = $b->target_date?->getTimestamp() ?? PHP_INT_MAX;

                return $da <=> $db;
            })
            ->values();
    }
}
