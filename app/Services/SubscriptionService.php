<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\PlanSlug;
use App\Enums\SubscriptionStatus;
use App\Models\Household;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Rieles de monetización (Épica 12, v0.39).
 *
 * Seam (ADR-0010): NO depende de la capa HTTP. Recibe modelos/valores
 * explícitos y devuelve resultados. Toda comprobación de plan/feature/límite
 * pasa por aquí — nunca desde un flag de cliente, nunca dentro de una
 * vista. Así la Épica 14 (API móvil) reusa la puerta sin reescribirla.
 */
class SubscriptionService
{
    /** Caché por request para no repetir la consulta en cada gate. */
    private array $planCache = [];

    /**
     * Plan efectivo del hogar HOY. Sin suscripción vigente → Free.
     *
     * "Efectivo" es lo que otorga plan (Active/Trialing con `ends_at` en
     * el futuro o `null`). Un `canceled` o `past_due`, o un `active`
     * caducado, caen a Free automáticamente.
     */
    public function planFor(Household $household): Plan
    {
        if (isset($this->planCache[$household->id])) {
            return $this->planCache[$household->id];
        }

        if ($this->premiumForAll()) {
            return $this->planCache[$household->id] = $this->requirePremiumPlan();
        }

        $subscription = $this->activeSubscription($household);
        $slug = $subscription?->isCurrentlyActive()
            ? $subscription->plan->slug
            : PlanSlug::default()->value;

        $plan = Plan::firstWhere('slug', $slug) ?? $this->requireFreePlan();

        return $this->planCache[$household->id] = $plan;
    }

    /**
     * ¿Está activo el interruptor "todos en Premium mientras no haya precio"?
     */
    public function premiumForAll(): bool
    {
        return (bool) Config::get('finlia.subscription.premium_for_all', false);
    }

    /**
     * Devuelve la suscripción del hogar (activa o no) o null si no tiene.
     * NO fuerza que sea "currentlyActive": ver `planFor()` para eso.
     */
    public function activeSubscription(Household $household): ?Subscription
    {
        return $household->subscription()->with('plan')->first();
    }

    public function hasFeature(Household $household, PlanFeature|string $feature): bool
    {
        return $this->planFor($household)->hasFeature($feature);
    }

    /**
     * ¿Cabe una acción de conteo `$currentCount + 1` dentro del límite?
     *
     * IMPORTANTE — Grandfather: este método aplica al MOMENTO de crear/
     * invitar (chequeo hacia adelante), NUNCA al estado histórico. El
     * caller pasa el conteo ANTES de la acción; devolvemos true si al
     * sumar uno todavía se está dentro del tope. Si un hogar existente
     * ya excede el tope, no se rompe: nunca se les pide bajar.
     *
     * `null` como tope = ilimitado (Premium).
     */
    public function withinLimit(Household $household, PlanLimit|string $limit, int $currentCount): bool
    {
        $cap = $this->planFor($household)->limit($limit);

        if ($cap === null) {
            return true;
        }

        return ($currentCount + 1) <= $cap;
    }

    /**
     * Cambia el plan efectivo del hogar a Premium hasta la fecha dada.
     *
     * Sin pasarela (v0.39): esto lo dispara el comando artisan
     * `finlia:grant-premium`. La `reason` queda registrada para auditoría.
     */
    public function grantPremium(
        Household $household,
        ?CarbonInterface $until = null,
        ?string $reason = null,
    ): Subscription {
        $premium = Plan::firstWhere('slug', PlanSlug::Premium->value)
            ?? throw new DomainException('El plan Premium no existe. Corre las migraciones.');

        return $this->assign($household, $premium, SubscriptionStatus::Active, $until, $reason);
    }

    /**
     * Vuelve al hogar al plan Free. Idempotente.
     */
    public function revoke(Household $household, ?string $reason = null): Subscription
    {
        $free = $this->requireFreePlan();

        $subscription = $this->assign($household, $free, SubscriptionStatus::Active, null, $reason);
        $subscription->canceled_at = now();
        $subscription->save();

        return $subscription;
    }

    /**
     * Asegura que el hogar tenga una suscripción (idempotente).
     * Se llama al crear un hogar para que el gate siempre encuentre fila.
     */
    public function ensureSubscription(Household $household): Subscription
    {
        $existing = $this->activeSubscription($household);
        if ($existing !== null) {
            return $existing;
        }

        return $this->assign($household, $this->requireFreePlan(), SubscriptionStatus::Active, null, 'Alta de hogar');
    }

    /**
     * ¿El usuario puede crear un nuevo hogar bajo su plan actual?
     *
     * El plan es del HOGAR pero el límite `HouseholdsCreated` es del USUARIO.
     * Regla: si el usuario administra AL MENOS un hogar Premium vigente, no
     * hay tope. Si no, aplica el tope del plan Free contra el conteo de
     * hogares que ya administra. Grandfather: quien ya tiene más hogares
     * de los que Free permite conserva los suyos, pero no puede crear otro
     * hasta pasar a Premium.
     */
    public function canUserCreateHousehold(User $user): bool
    {
        if ($this->premiumForAll()) {
            return true;
        }

        $owned = $user->ownedHouseholds()->with(['subscription.plan'])->get();

        $hasPremium = $owned->contains(function (Household $h): bool {
            $sub = $h->subscription;

            return $sub !== null
                && $sub->isCurrentlyActive()
                && $sub->plan?->slug === PlanSlug::Premium->value;
        });

        if ($hasPremium) {
            return true;
        }

        $cap = $this->requireFreePlan()->limit(PlanLimit::HouseholdsCreated);
        if ($cap === null) {
            return true;
        }

        return $owned->count() < $cap;
    }

    /**
     * ¿Cabe un miembro más en el hogar? Cuenta a los miembros ya vinculados
     * (owner incluido). No considera invitaciones pendientes: puede haber
     * varias por errores o cambios de correo, y contarlas convertiría "queda
     * un cupo" en "no queda ninguno" por invitaciones caducadas.
     */
    public function canInviteMember(Household $household): bool
    {
        $memberCount = $household->members()->count();

        return $this->withinLimit($household, PlanLimit::MembersPerHousehold, $memberCount);
    }

    /**
     * Encuestas de compras respondidas por el hogar en el mes calendario dado
     * (o el mes en curso). Se usa para decidir si se dispara otra bajo Free.
     */
    public function compulsiveSurveysUsedThisMonth(Household $household, ?CarbonInterface $now = null): int
    {
        $reference = $now ?? now();
        $start = $reference->copy()->startOfMonth();
        $end = $reference->copy()->endOfMonth();

        return $household->compulsiveSurveyResponses()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * ¿Se le puede pedir al hogar una encuesta MÁS este mes?
     */
    public function canAskCompulsiveSurvey(Household $household, ?CarbonInterface $now = null): bool
    {
        if ($this->hasFeature($household, PlanFeature::UnlimitedSurveys)) {
            return true;
        }

        $used = $this->compulsiveSurveysUsedThisMonth($household, $now);

        return $this->withinLimit($household, PlanLimit::CompulsiveSurveysPerMonth, $used);
    }

    private function assign(
        Household $household,
        Plan $plan,
        SubscriptionStatus $status,
        ?CarbonInterface $endsAt,
        ?string $reason,
    ): Subscription {
        unset($this->planCache[$household->id]);

        return DB::transaction(function () use ($household, $plan, $status, $endsAt, $reason): Subscription {
            $subscription = $household->subscription()->firstOrNew([]);

            $subscription->plan_id = $plan->id;
            $subscription->status = $status;
            $subscription->started_at ??= now();
            $subscription->ends_at = $endsAt;
            $subscription->canceled_at = null;
            $subscription->reason = $reason;
            $subscription->household()->associate($household);
            $subscription->save();

            $subscription->setRelation('plan', $plan);

            return $subscription;
        });
    }

    private function requireFreePlan(): Plan
    {
        return Plan::firstWhere('slug', PlanSlug::Free->value)
            ?? throw new DomainException('El plan Free no existe. Corre las migraciones.');
    }

    private function requirePremiumPlan(): Plan
    {
        return Plan::firstWhere('slug', PlanSlug::Premium->value)
            ?? throw new DomainException('El plan Premium no existe. Corre las migraciones.');
    }
}
