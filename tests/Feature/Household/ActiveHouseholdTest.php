<?php

namespace Tests\Feature\Household;

use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveHouseholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_miembro_puede_cambiar_su_hogar_activo(): void
    {
        $user = User::factory()->create();
        $primero = app(HouseholdService::class)->createHousehold($user->id, 'Hogar 1');
        $segundo = app(HouseholdService::class)->createHousehold($user->id, 'Hogar 2');

        $response = $this->actingAs($user)->post(route('households.activate', $segundo));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('household_id', $segundo->id);
    }

    public function test_activar_otro_hogar_se_refleja_en_el_dashboard(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar Principal');
        $otro = app(HouseholdService::class)->createHousehold($user->id, 'Hogar Secundario');

        $this->actingAs($user)->post(route('households.activate', $otro));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hogar Secundario');
    }

    public function test_helper_resuelve_el_primer_hogar_si_no_hay_sesion(): void
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Único');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Único');
        $response->assertSessionHas('household_id', $household->id);
    }

    /**
     * Épica 3: el dashboard requiere hogar activo (calcula finanzas), así que
     * redirige al usuario sin hogares a la pantalla de creación (302). Antes
     * (Épica 2) renderizaba un CTA en el propio dashboard.
     */
    public function test_usuario_sin_hogares_es_llevado_a_crear(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('households.create'));
    }

    public function test_usuario_ajeno_no_puede_activar_hogar(): void
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Ajeno');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('households.activate', $household))
            ->assertForbidden();
    }
}
