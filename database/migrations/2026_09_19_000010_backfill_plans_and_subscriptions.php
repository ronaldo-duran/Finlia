<?php

declare(strict_types=1);

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\PlanSlug;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed base y grandfather de la Épica 12.
 *
 * 1) Crea los planes `free` y `premium` con sus features/limits actuales.
 *    Los precios de v0.39 (COP): mensual $9.900, anual $79.000 (escenario A).
 * 2) Toda `households` existente arranca con una `subscription` `active` al
 *    plan free. Los límites se enforzan hacia adelante (crear/invitar),
 *    NUNCA hacia atrás (docs/DECISIONS.md ADR de la Épica 12).
 *
 * Esta migración es un backfill idempotente: usa `updateOrInsert` sobre
 * `plans.slug` y `firstOrCreate`-style sobre `subscriptions.household_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $freeFeatures = [
            PlanFeature::PdfReports->value => false,
            PlanFeature::ChatAi->value => false,
            PlanFeature::CompulsiveInsights->value => false,
            PlanFeature::ExtendedHistory->value => false,
            PlanFeature::UnlimitedSurveys->value => false,
        ];

        $premiumFeatures = [
            // v0.39: rieles listos, features aún no encendidas.
            // Se activan una a una en versiones posteriores (chat IA con BYOK,
            // PDF de reportes, autoconocimiento). Cada feature `true` que se
            // añada aquí queda automáticamente disponible para Premium.
            PlanFeature::PdfReports->value => false,
            PlanFeature::ChatAi->value => false,
            PlanFeature::CompulsiveInsights->value => false,
            PlanFeature::ExtendedHistory->value => false,
            PlanFeature::UnlimitedSurveys->value => true, // única encendida en v0.39
        ];

        $freeLimits = [
            PlanLimit::HouseholdsCreated->value => 1,
            PlanLimit::MembersPerHousehold->value => 2,
            PlanLimit::CompulsiveSurveysPerMonth->value => 5,
        ];

        $premiumLimits = [
            PlanLimit::HouseholdsCreated->value => null,
            PlanLimit::MembersPerHousehold->value => null,
            PlanLimit::CompulsiveSurveysPerMonth->value => null,
        ];

        DB::table('plans')->updateOrInsert(
            ['slug' => PlanSlug::Free->value],
            [
                'name' => 'Gratis',
                'price_monthly' => null,
                'price_yearly' => null,
                'features' => json_encode($freeFeatures),
                'limits' => json_encode($freeLimits),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('plans')->updateOrInsert(
            ['slug' => PlanSlug::Premium->value],
            [
                'name' => 'Premium',
                'price_monthly' => 9900.00,
                'price_yearly' => 79000.00,
                'features' => json_encode($premiumFeatures),
                'limits' => json_encode($premiumLimits),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $freePlanId = DB::table('plans')->where('slug', PlanSlug::Free->value)->value('id');

        // Grandfather: todo hogar existente arranca en Free ACTIVO.
        $householdIds = DB::table('households')->pluck('id');

        foreach ($householdIds as $householdId) {
            DB::table('subscriptions')->updateOrInsert(
                ['household_id' => $householdId],
                [
                    'plan_id' => $freePlanId,
                    'status' => SubscriptionStatus::Active->value,
                    'started_at' => $now,
                    'renews_at' => null,
                    'ends_at' => null,
                    'canceled_at' => null,
                    'reason' => 'Backfill v0.39',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('reason', 'Backfill v0.39')->delete();
        DB::table('plans')->whereIn('slug', [PlanSlug::Free->value, PlanSlug::Premium->value])->delete();
    }
};
