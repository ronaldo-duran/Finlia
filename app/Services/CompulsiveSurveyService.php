<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Models\Budget;
use App\Models\CompulsiveSurveyResponse;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;

/**
 * Árbol de decisión de compras (Épica 12).
 *
 * Se ofrece el árbol SÓLO cuando el gasto no cabe en el presupuesto del mes
 * — un gasto imprevisto es donde la pregunta tiene sentido; sobre uno
 * planeado no hay nada que reflexionar. El azar sale del disparo: la señal
 * es la ausencia de un `Budget` para (categoría del gasto, año y mes del
 * gasto). Siempre es opcional (el modal se puede cerrar sin responder).
 *
 * Freemium: 5 respuestas al mes en Free (feature `unlimited_surveys`
 * apagada), ilimitadas en Premium. Sin cupo, no se ofrece más.
 *
 * Seam (ADR-0010): recibe modelos explícitos y no lee `session()`/`request()`.
 * El controlador HTTP se encarga de disparar `shouldOffer()` y de guardar
 * la respuesta cuando llega el POST.
 */
class CompulsiveSurveyService
{
    /**
     * Distancia por defecto entre el registro y el seguimiento — cuánto tiempo
     * después de la compra se le vuelve a preguntar al usuario cómo se siente.
     */
    public const FOLLOW_UP_DAYS = 30;

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * ¿Se le ofrece el árbol al usuario justo después de registrar el gasto?
     *
     * Reglas:
     *   1. El hogar debe tener cupo (freemium/Premium — via SubscriptionService).
     *   2. El gasto no debe haber sido encuestado ya (unique).
     *   3. El gasto NO tiene un presupuesto de esa categoría para su mes —
     *      es decir, es imprevisto respecto a lo planeado.
     *   4. Kill switch global `finlia.compulsive_survey.enabled` (default true).
     */
    public function shouldOffer(Household $household, Expense $expense): bool
    {
        if (! config('finlia.compulsive_survey.enabled', true)) {
            return false;
        }

        if (! $this->subscriptions->canAskCompulsiveSurvey($household)) {
            return false;
        }

        if (CompulsiveSurveyResponse::where('expense_id', $expense->id)->exists()) {
            return false;
        }

        return $this->isUnbudgeted($household, $expense);
    }

    /**
     * ¿Este gasto queda fuera del presupuesto planeado del hogar?
     *
     * Se consulta la tabla `budgets` por (household, category, año, mes) del
     * gasto. Un gasto sin categoría se trata como imprevisto por definición.
     * El presupuesto total del mes (`category_id` NULL) no cuenta como
     * "planeado para esta categoría": si el usuario definió un total pero
     * no un renglón para la categoría del gasto, sigue siendo imprevisto.
     */
    private function isUnbudgeted(Household $household, Expense $expense): bool
    {
        if ($expense->category_id === null) {
            return true;
        }

        $date = $expense->date ?? now();

        return ! Budget::query()
            ->where('household_id', $household->id)
            ->where('category_id', $expense->category_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->exists();
    }

    /**
     * Registra la respuesta al árbol. Idempotente por `expense_id` — un doble
     * envío del formulario no crea dos filas ni sube el contador.
     */
    public function record(
        Household $household,
        User $user,
        Expense $expense,
        CompulsivePlanned $planned,
        CompulsiveKind $kind,
        CompulsiveMood $mood,
        CompulsiveTrigger $trigger,
    ): CompulsiveSurveyResponse {
        // household_id se asigna directamente (fuera de mass assignment) para
        // seguir la convención del proyecto: los `household_id` no llegan por
        // fillable, los pone el service desde el hogar autenticado.
        $response = CompulsiveSurveyResponse::firstOrNew(['expense_id' => $expense->id]);
        $response->household_id = $household->id;
        $response->user_id = $user->id;
        $response->planned = $planned;
        $response->kind = $kind;
        $response->mood = $mood;
        $response->trigger = $trigger;

        // Snapshot demográfico: se congela al momento de la respuesta. `birth_date`
        // puede ser null (perfil incompleto) — entonces la edad también lo es;
        // el género se copia tal cual (string, validado por `Gender` en el
        // formulario de perfil).
        $response->age_years = $user->age();
        $response->gender = $user->gender;

        // Se agenda una cita de seguimiento sólo al crear la respuesta: si el
        // formulario se reenvía en el mismo instante, el `due_at` original
        // sobrevive. `null` en las columnas de respuesta significa "pendiente".
        if (! $response->exists) {
            $response->follow_up_due_at = now()->addDays(self::FOLLOW_UP_DAYS);
        }

        $response->save();

        return $response;
    }

    /**
     * Registra la respuesta al seguimiento (a los ~30 días de la compra).
     * `regret` y `note` son opcionales — la única obligación es el `moodAfter`.
     */
    public function answerFollowUp(
        CompulsiveSurveyResponse $response,
        CompulsiveMood $moodAfter,
        ?bool $regret = null,
        ?string $note = null,
    ): CompulsiveSurveyResponse {
        $response->mood_after = $moodAfter;
        $response->follow_up_regret = $regret;
        $response->follow_up_note = $note;
        $response->follow_up_answered_at = now();
        $response->save();

        return $response;
    }
}
