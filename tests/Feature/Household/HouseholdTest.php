<?php

namespace Tests\Feature\Household;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(?string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);

        return [$owner, $household];
    }

    private function setupHouseholdWithMember(): array
    {
        [$owner, $household] = $this->setupHousehold();

        $member = User::factory()->create();
        $household->members()->attach($member->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);

        return [$owner, $household, $member];
    }

    public function test_guest_es_redirigido_al_login_al_ver_hogares(): void
    {
        $this->get(route('households.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_puede_listar_sus_hogares(): void
    {
        [$owner, $household] = $this->setupHousehold('Familia Tester');

        $this->actingAs($owner)
            ->get(route('households.index'))
            ->assertOk()
            ->assertSee('Familia Tester');
    }

    public function test_usuario_puede_crear_un_hogar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Hogar Nuevo',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ]);

        $household = Household::where('owner_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('households.show', $household));

        $this->assertDatabaseHas('households', [
            'name' => 'Hogar Nuevo',
            'owner_id' => $user->id,
        ]);
        // El creador queda vinculado como administrador.
        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_creacion_valida_campos_obligatorios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('households.store'), [
            'name' => '',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ])->assertSessionHasErrors('name');
    }

    public function test_miembro_puede_ver_el_hogar(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();

        $this->actingAs($member)
            ->get(route('households.show', $household))
            ->assertOk()
            ->assertSee($household->name);
    }

    public function test_owner_puede_actualizar_el_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->put(route('households.update', $household), [
            'name' => 'Renombrado',
            'currency' => 'USD',
            'timezone' => 'America/Bogota',
        ])->assertRedirect(route('households.show', $household));

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Renombrado',
            'currency' => 'USD',
        ]);
    }

    public function test_miembro_no_puede_actualizar_el_hogar(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();

        $this->actingAs($member)->put(route('households.update', $household), [
            'name' => 'Hack',
            'currency' => 'USD',
            'timezone' => 'America/Bogota',
        ])->assertForbidden();
    }

    public function test_owner_puede_eliminar_el_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)
            ->delete(route('households.destroy', $household))
            ->assertRedirect(route('households.index'));

        $this->assertSoftDeleted('households', ['id' => $household->id]);
    }

    public function test_registro_crea_hogar_personal_automatico(): void
    {
        $this->post(route('register'), [
            'name' => 'Ana Prueba',
            'email' => 'ana@finlia.test',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]);

        $user = User::where('email', 'ana@finlia.test')->firstOrFail();

        $this->assertDatabaseHas('households', ['owner_id' => $user->id, 'name' => 'Mi hogar']);
        $this->assertDatabaseHas('household_user', ['user_id' => $user->id, 'role' => 'owner']);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_ver_hogar_de_otro(): void
    {
        [, $household] = $this->setupHousehold();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('households.show', $household))
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_editar_hogar_de_otro(): void
    {
        [, $household] = $this->setupHousehold();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('households.update', $household), [
            'name' => 'Hack',
            'currency' => 'COP',
            'timezone' => 'America/Bogota',
        ])->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_eliminar_hogar_de_otro(): void
    {
        [, $household] = $this->setupHousehold();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete(route('households.destroy', $household))
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_activar_hogar_de_otro(): void
    {
        [, $household] = $this->setupHousehold();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('households.activate', $household))
            ->assertForbidden();
    }

    public function test_miembro_no_puede_expulsar_a_otro_miembro(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();
        $otro = User::factory()->create();
        $household->members()->attach($otro->id, ['role' => HouseholdRole::Member->value, 'joined_at' => now()]);

        $this->actingAs($member)
            ->delete(route('households.members.destroy', [$household, $otro]))
            ->assertForbidden();
    }

    public function test_owner_no_puede_expulsarse_a_si_mismo(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)
            ->delete(route('households.members.destroy', [$household, $owner]))
            ->assertSessionHasErrors();
    }

    public function test_owner_puede_expulsar_a_un_miembro(): void
    {
        [$owner, $household, $member] = $this->setupHouseholdWithMember();

        $this->actingAs($owner)
            ->delete(route('households.members.destroy', [$household, $member]))
            ->assertRedirect(route('households.show', $household));

        $this->assertFalse($household->fresh()->hasMember($member));
    }
}
