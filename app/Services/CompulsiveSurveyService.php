<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Models\CompulsiveSurveyResponse;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;

/**
 * Árbol de decisión de compras (Épica 12, v0.39).
 *
 * Al registrar un gasto se ofrece el árbol con probabilidad configurable.
 * El objetivo es doble:
 *   - dar al usuario un espejo sobre por qué compra (autoconocimiento);
 *   - acumular un dataset para ML futuro.
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
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * ¿Se le ofrece el árbol al usuario justo después de registrar el gasto?
     *
     * Reglas:
     *   1. El hogar debe tener cupo (freemium/Premium — via SubscriptionService).
     *   2. El gasto no debe haber sido encuestado ya (unique).
     *   3. Probabilidad `probability_percent` (config/finlia.php).
     */
    public function shouldOffer(Household $household, Expense $expense): bool
    {
        if (! $this->subscriptions->canAskCompulsiveSurvey($household)) {
            return false;
        }

        if (CompulsiveSurveyResponse::where('expense_id', $expense->id)->exists()) {
            return false;
        }

        $probability = max(0, min(100, (int) config('finlia.compulsive_survey.probability_percent', 15)));
        if ($probability === 0) {
            return false;
        }

        return random_int(1, 100) <= $probability;
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
        $response->save();

        return $response;
    }
}
