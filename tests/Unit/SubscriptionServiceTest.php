<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\PlanSlug;
use App\Models\Household;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('finlia.subscription.premium_for_all', false);
    }

    private function makeHousehold(): Household
    {
        $owner = User::factory()->create();

        return app(HouseholdService::class)->createHousehold($owner->id, 'Hogar Test');
    }

    public function test_plan_for_devuelve_free_por_defecto_para_hogar_nuevo(): void
    {
        $household = $this->makeHousehold();

        $plan = app(SubscriptionService::class)->planFor($household);

        $this->assertSame(PlanSlug::Free->value, $plan->slug);
    }

    public function test_grant_premium_cambia_el_plan_efectivo(): void
    {
        $household = $this->makeHousehold();
        $service = app(SubscriptionService::class);

        $service->grantPremium($household, now()->addMonth(), 'Prueba');

        $this->assertSame(PlanSlug::Premium->value, $service->planFor($household->fresh())->slug);
    }

    public function test_premium_expirado_vuelve_a_free_efectivamente(): void
    {
        $household = $this->makeHousehold();
        $service = app(SubscriptionService::class);

        $service->grantPremium($household, now()->subDay(), 'Expirado');

        $this->assertSame(PlanSlug::Free->value, $service->planFor($household->fresh())->slug);
    }

    public function test_within_limit_respeta_grandfather_sin_bloquear_el_estado(): void
    {
        $household = $this->makeHousehold();
        $service = app(SubscriptionService::class);

        $this->assertTrue($service->withinLimit($household, PlanLimit::MembersPerHousehold, 1));
        $this->assertFalse($service->withinLimit($household, PlanLimit::MembersPerHousehold, 2));
        $this->assertFalse($service->withinLimit($household, PlanLimit::MembersPerHousehold, 5));
    }

    public function test_features_se_leen_desde_el_plan(): void
    {
        $household = $this->makeHousehold();
        $service = app(SubscriptionService::class);

        $this->assertFalse($service->hasFeature($household, PlanFeature::UnlimitedSurveys));

        $service->grantPremium($household, now()->addMonth(), 'Prueba');

        $this->assertTrue($service->hasFeature($household->fresh(), PlanFeature::UnlimitedSurveys));
    }

    public function test_can_user_create_household_bloquea_segundo_hogar_en_free(): void
    {
        $user = User::factory()->create();
        $service = app(SubscriptionService::class);
        $households = app(HouseholdService::class);

        $households->createHousehold($user->id, 'Uno');
        $this->assertFalse($service->canUserCreateHousehold($user->fresh()));
    }

    public function test_can_user_create_household_permite_mas_hogares_con_premium(): void
    {
        $user = User::factory()->create();
        $service = app(SubscriptionService::class);
        $households = app(HouseholdService::class);

        $first = $households->createHousehold($user->id, 'Uno');
        $service->grantPremium($first, now()->addMonth(), 'Prueba');

        $this->assertTrue($service->canUserCreateHousehold($user->fresh()));
    }

    public function test_migracion_de_backfill_creo_ambos_planes(): void
    {
        $this->assertNotNull(Plan::firstWhere('slug', PlanSlug::Free->value));

        $premium = Plan::firstWhere('slug', PlanSlug::Premium->value);
        $this->assertNotNull($premium);
        $this->assertSame('9900.00', $premium->price_monthly);
        $this->assertSame('79000.00', $premium->price_yearly);
    }

    public function test_una_suscripcion_por_hogar(): void
    {
        $household = $this->makeHousehold();

        $this->assertSame(1, Subscription::where('household_id', $household->id)->count());

        app(SubscriptionService::class)->grantPremium($household, now()->addMonth(), 'Prueba');

        $this->assertSame(1, Subscription::where('household_id', $household->id)->count());
    }

    public function test_premium_for_all_devuelve_premium_para_todo_hogar(): void
    {
        Config::set('finlia.subscription.premium_for_all', true);

        $household = $this->makeHousehold();
        $service = app(SubscriptionService::class);

        $this->assertSame(PlanSlug::Premium->value, $service->planFor($household)->slug);
        $this->assertTrue($service->canUserCreateHousehold($household->owner));
        $this->assertTrue($service->canInviteMember($household));
        $this->assertTrue($service->canAskCompulsiveSurvey($household));
    }
}
