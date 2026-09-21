<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Pantalla /perfil/plan (Épica 12, v0.39).
 *
 * La app arranca con `premium_for_all` encendido; estos tests observan el
 * comportamiento real de la pantalla apagándolo, salvo el que confirma
 * que con el flag encendido todos ven Premium.
 */
class ProfilePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('finlia.subscription.premium_for_all', false);
    }

    public function test_muestra_plan_free_al_hogar_recien_creado(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar');

        $this->actingAs($user)
            ->get(route('profile.plan'))
            ->assertOk()
            ->assertSeeText('Gratis')
            ->assertSeeText('Premium');
    }

    public function test_muestra_plan_premium_cuando_esta_activo(): void
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Hogar');
        app(SubscriptionService::class)->grantPremium($household, now()->addMonth(), 'Prueba');

        $this->actingAs($user)
            ->get(route('profile.plan'))
            ->assertOk()
            ->assertSeeText('Premium')
            ->assertSeeText('Vence el');
    }

    public function test_sin_hogar_redirige_a_crear(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.plan'))
            ->assertRedirect(route('households.create'));
    }

    public function test_premium_for_all_abre_premium_a_todos(): void
    {
        Config::set('finlia.subscription.premium_for_all', true);

        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar');

        $this->actingAs($user)
            ->get(route('profile.plan'))
            ->assertOk()
            ->assertSeeText('Premium')
            ->assertSeeText('Mientras Finlia termina de definir qué lleva Premium');
    }
}
