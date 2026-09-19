<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Límites numéricos configurables por plan (Épica 12).
 *
 * `null` en el mapa `limits` del plan = sin límite (Premium). Un entero es
 * el tope duro; el chequeo aplica al MOMENTO de crear/invitar, nunca
 * al estado histórico (ver `SubscriptionService::withinLimit()` y ADR
 * de la Épica 12). Así los hogares creados antes del lanzamiento con
 * más miembros o más hogares por usuario NO se rompen.
 */
enum PlanLimit: string
{
    /** Hogares que un usuario puede haber creado (owner). */
    case HouseholdsCreated = 'households_created';

    /** Miembros totales de un hogar (owner + invitados). */
    case MembersPerHousehold = 'members_per_household';

    /** Respuestas a la encuesta de compras por mes calendario. */
    case CompulsiveSurveysPerMonth = 'compulsive_surveys_per_month';

    public function label(): string
    {
        return match ($this) {
            self::HouseholdsCreated => 'Hogares por usuario',
            self::MembersPerHousehold => 'Miembros por hogar',
            self::CompulsiveSurveysPerMonth => 'Encuestas de compras al mes',
        };
    }
}
