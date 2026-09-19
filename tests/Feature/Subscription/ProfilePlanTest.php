<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla /perfil/plan (Épica 12, v0.38).
 */
class ProfilePlanTest extends TestCase
{
    use RefreshDatabase;

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
}
