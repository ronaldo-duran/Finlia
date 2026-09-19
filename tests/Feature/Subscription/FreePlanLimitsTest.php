<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Enums\HouseholdRole;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforcement de los límites del plan Free (Épica 12, v0.38).
 * Grandfather: el chequeo aplica al crear/invitar, no al estado histórico.
 */
class FreePlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_no_permite_crear_un_segundo_hogar(): void
    {
        $user = User::factory()->create();
        $service = app(HouseholdService::class);
        $service->createHousehold($user->id, 'Primero');

        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Segundo',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertRedirect(route('households.index'));
        $this->assertSame(1, $user->fresh()->ownedHouseholds()->count());
    }

    public function test_premium_permite_crear_mas_de_un_hogar(): void
    {
        $user = User::factory()->create();
        $service = app(HouseholdService::class);
        $subs = app(SubscriptionService::class);

        $first = $service->createHousehold($user->id, 'Primero');
        $subs->grantPremium($first, now()->addMonth(), 'Prueba');

        $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Segundo',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ])->assertRedirect();

        $this->assertSame(2, $user->fresh()->ownedHouseholds()->count());
    }

    public function test_free_no_permite_invitar_un_tercer_miembro(): void
    {
        $user = User::factory()->create();
        $service = app(HouseholdService::class);
        $household = $service->createHousehold($user->id, 'Hogar');

        // Owner (1) + un miembro = 2 personas. Free tope 2 → no cabe otro.
        $existing = User::factory()->create();
        $household->members()->attach($existing->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)->post(route('households.invitations.store', $household), [
            'email' => 'tercero@finlia.test',
            'role' => 'member',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('household_invitations', [
            'household_id' => $household->id,
            'email' => 'tercero@finlia.test',
        ]);
    }

    public function test_grandfather_hogar_con_mas_miembros_sigue_funcionando(): void
    {
        $user = User::factory()->create();
        $service = app(HouseholdService::class);
        $household = $service->createHousehold($user->id, 'Hogar Grande');

        // Simula grandfather: el hogar ya tiene 4 miembros extra (5 total)
        // desde antes de la Épica 12.
        for ($i = 0; $i < 4; $i++) {
            $member = User::factory()->create();
            $household->members()->attach($member->id, [
                'role' => HouseholdRole::Member->value,
                'joined_at' => now()->subMonths(6),
            ]);
        }

        // La suscripción sigue siendo Free, pero los datos no se rompen.
        $this->assertSame(5, $household->members()->count());

        // Solo se bloquea el SIGUIENTE invitado (chequeo hacia adelante).
        $this->actingAs($user)->post(route('households.invitations.store', $household), [
            'email' => 'sexto@finlia.test',
            'role' => 'member',
        ])->assertSessionHasErrors('email');
    }
}
