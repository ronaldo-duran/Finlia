<?php

namespace Tests\Feature\Household;

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HouseholdInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar Invitaciones');

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

    public function test_owner_puede_crear_una_invitacion(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('households.invitations.store', $household), [
            'email' => 'amigo@finlia.test',
            'role' => 'member',
        ])->assertRedirect(route('households.show', $household));

        $this->assertDatabaseHas('household_invitations', [
            'household_id' => $household->id,
            'email' => 'amigo@finlia.test',
            'status' => 'pending',
        ]);
    }

    public function test_miembro_no_puede_invitar(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();

        $this->actingAs($member)->post(route('households.invitations.store', $household), [
            'email' => 'alguien@finlia.test',
            'role' => 'member',
        ])->assertForbidden();
    }

    public function test_no_se_puede_invitar_a_un_usuario_que_ya_es_miembro(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();

        $this->actingAs(User::find($household->owner_id))
            ->post(route('households.invitations.store', $household), [
                'email' => $member->email,
                'role' => 'member',
            ])->assertSessionHasErrors('email');
    }

    public function test_el_token_se_almacena_hasheado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [$invitation, $plainToken] = app(HouseholdService::class)
            ->inviteMember($household, 'inv@finlia.test', HouseholdRole::Member);

        // El token plano no debe estar en la base de datos.
        $this->assertDatabaseMissing('household_invitations', ['token' => $plainToken]);
        $this->assertSame(hash('sha256', $plainToken), $invitation->fresh()->token);
    }

    public function test_usuario_puede_aceptar_una_invitacion_valida(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [$invitation, $plainToken] = app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member);

        $guest = User::factory()->create(['email' => 'invitado@finlia.test']);

        $this->actingAs($guest)
            ->post(route('invitations.accept', $plainToken))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($household->fresh()->hasMember($guest));
        $this->assertDatabaseHas('household_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
            'accepted_by_user_id' => $guest->id,
        ]);
    }

    public function test_correo_distinto_no_puede_aceptar(): void
    {
        [, $household] = $this->setupHousehold();
        [$invitation, $plainToken] = app(HouseholdService::class)
            ->inviteMember($household, 'invitado@finlia.test', HouseholdRole::Member);

        $otro = User::factory()->create(['email' => 'otro@finlia.test']);

        $this->actingAs($otro)
            ->post(route('invitations.accept', $plainToken))
            ->assertSessionHasErrors('token');

        $this->assertFalse($household->fresh()->hasMember($otro));
        $this->assertSame('pending', $invitation->fresh()->status->value);
    }

    public function test_invitacion_expirada_no_se_acepta(): void
    {
        [, $household] = $this->setupHousehold();
        [$invitation, $plainToken] = app(HouseholdService::class)
            ->inviteMember($household, 'inv@finlia.test', HouseholdRole::Member);

        $invitation->update(['expires_at' => now()->subDay()]);

        $guest = User::factory()->create(['email' => 'inv@finlia.test']);

        $this->actingAs($guest)
            ->post(route('invitations.accept', $plainToken))
            ->assertSessionHasErrors('token');

        $this->assertSame('expired', $invitation->fresh()->status->value);
    }

    public function test_token_invalido_devuelve_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invitations.show', Str::random(64)))
            ->assertNotFound();
    }

    public function test_owner_puede_revocar_una_invitacion(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [$invitation] = app(HouseholdService::class)
            ->inviteMember($household, 'inv@finlia.test', HouseholdRole::Member);

        $this->actingAs($owner)
            ->delete(route('households.invitations.destroy', [$household, $invitation]))
            ->assertRedirect(route('households.show', $household));

        $this->assertSame('revoked', $invitation->fresh()->status->value);
    }

    public function test_miembro_no_puede_revocar_una_invitacion(): void
    {
        [, $household, $member] = $this->setupHouseholdWithMember();
        /** @var HouseholdInvitation $invitation */
        $invitation = HouseholdInvitation::factory()->create(['household_id' => $household->id]);

        $this->actingAs($member)
            ->delete(route('households.invitations.destroy', [$household, $invitation]))
            ->assertForbidden();
    }
}
