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
 * Laravel corre cada migración una sola vez (registro en la tabla `migrations`),
 * así que aquí no hay guardas de "si ya existe" — se inserta directo. Los
 * planes `free` y `premium` nacen con sus features/limits actuales y toda
 * `households` existente estrena una `subscription` `active` al plan Free.
 * Los límites se enforzan hacia adelante (crear/invitar), NUNCA hacia atrás
 * (docs/DECISIONS.md ADR-0046).
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
            PlanFeature::PdfReports->value => false,
            PlanFeature::ChatAi->value => false,
            PlanFeature::CompulsiveInsights->value => false,
            PlanFeature::ExtendedHistory->value => false,
            PlanFeature::UnlimitedSurveys->value => true,
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

        $freePlanId = DB::table('plans')->insertGetId([
            'slug' => PlanSlug::Free->value,
            'name' => 'Gratis',
            'price_monthly' => null,
            'price_yearly' => null,
            'features' => json_encode($freeFeatures),
            'limits' => json_encode($freeLimits),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('plans')->insert([
            'slug' => PlanSlug::Premium->value,
            'name' => 'Premium',
            'price_monthly' => 9900.00,
            'price_yearly' => 79000.00,
            'features' => json_encode($premiumFeatures),
            'limits' => json_encode($premiumLimits),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Grandfather: todo hogar existente arranca en Free ACTIVO.
        $householdIds = DB::table('households')->pluck('id');
        $rows = $householdIds->map(fn ($id) => [
            'household_id' => $id,
            'plan_id' => $freePlanId,
            'status' => SubscriptionStatus::Active->value,
            'started_at' => $now,
            'renews_at' => null,
            'ends_at' => null,
            'canceled_at' => null,
            'reason' => 'Backfill v0.39',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('subscriptions')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('reason', 'Backfill v0.39')->delete();
        DB::table('plans')->whereIn('slug', [PlanSlug::Free->value, PlanSlug::Premium->value])->delete();
    }
};
