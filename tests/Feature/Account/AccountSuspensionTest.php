<?php

namespace Tests\Feature\Account;

use App\Enums\HouseholdRole;
use App\Mail\AccountDeletionRequestedMail;
use App\Models\Household;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Eliminación de cuenta: suspensión 30 días y purga (Plan 05, ADR-0033).
 *
 * Cubre:
 *  - La cuenta suspendida bloquea la navegación y redirige a /cuenta/suspendida.
 *  - El usuario puede reactivar su cuenta desde /cuenta/suspendida.
 *  - La solicitud de eliminación requiere contraseña, marca la suspensión y cierra sesión.
 *  - El digest de recordatorios excluye cuentas suspendidas.
 *  - Las reglas de purga: cascade, transferencia y anonimización.
 */
class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Middleware y navegación
    // -----------------------------------------------------------------------

    public function test_cuenta_activa_puede_acceder_al_dashboard(): void
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar A');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_cuenta_suspendida_redirige_a_pagina_de_suspension(): void
    {
        $user = User::factory()->create();
        $user->deletion_requested_at = now();
        $user->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.suspended'));
    }

    public function test_cuenta_suspendida_puede_ver_la_pagina_de_suspension(): void
    {
        $user = User::factory()->create();
        $user->deletion_requested_at = now()->subDays(2);
        $user->save();

        $this->actingAs($user)
            ->get(route('account.suspended'))
            ->assertOk()
            ->assertSee('Reactivar');
    }

    public function test_cuenta_activa_al_acceder_a_suspension_redirige_al_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.suspended'))
            ->assertRedirect(route('dashboard'));
    }

    // -----------------------------------------------------------------------
    // Reactivación
    // -----------------------------------------------------------------------

    public function test_reactivar_cuenta_limpia_deletion_requested_at(): void
    {
        $user = User::factory()->create();
        $user->deletion_requested_at = now()->subDays(5);
        $user->save();

        $this->actingAs($user)
            ->post(route('account.reactivate'))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_reactivar_cuenta_activa_no_hace_nada_y_redirige(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.reactivate'))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    // -----------------------------------------------------------------------
    // Solicitud de eliminación (ProfileController)
    // -----------------------------------------------------------------------

    public function test_solicitud_de_eliminacion_requiere_autenticacion(): void
    {
        $this->delete(route('profile.deletion.store'))->assertRedirect(route('login'));
    }

    public function test_solicitud_de_eliminacion_requiere_contrasena(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.deletion.store'), [
                'current_password' => 'contraseña-incorrecta',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_solicitud_de_eliminacion_marca_suspension_y_cierra_sesion(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->delete(route('profile.deletion.store'), [
                'current_password' => 'secret123',
            ])
            ->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->deletion_requested_at);
        $this->assertGuest();
    }

    public function test_solicitud_de_eliminacion_envia_correo_antifraude(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->delete(route('profile.deletion.store'), [
                'current_password' => 'secret123',
            ]);

        Mail::assertSent(AccountDeletionRequestedMail::class, function ($mail) use ($user) {
            return $mail->userName === $user->name;
        });
    }

    // -----------------------------------------------------------------------
    // Reglas de purga
    // -----------------------------------------------------------------------

    public function test_purga_anonimiza_usuario_miembro(): void
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $member = User::factory()->create();
        $member->deletion_requested_at = now()->subDays(31);
        $member->save();
        $household->members()->attach($member->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);

        app(AccountDeletionService::class)->purge($member);

        $anon = $member->fresh();
        $this->assertStringStartsWith('deleted+', $anon->email);
        $this->assertEquals('Usuario eliminado', $anon->name);
        $this->assertNull($anon->deletion_requested_at);
    }

    public function test_purga_dueno_unico_borra_el_hogar_en_cascada(): void
    {
        $owner = User::factory()->create();
        $owner->deletion_requested_at = now()->subDays(31);
        $owner->save();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar Solo');

        app(AccountDeletionService::class)->purge($owner);

        $this->assertNull(\App\Models\Household::withTrashed()->find($household->id));
    }

    public function test_purga_transfiere_ownership_al_miembro_mas_antiguo(): void
    {
        $owner = User::factory()->create();
        $owner->deletion_requested_at = now()->subDays(31);
        $owner->save();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar Compartido');

        $member = User::factory()->create();
        $household->members()->attach($member->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now()->subDays(10),
        ]);

        app(AccountDeletionService::class)->purge($owner);

        $this->assertEquals($member->id, $household->fresh()->owner_id);
        // El hogar persiste.
        $this->assertNotNull(\App\Models\Household::find($household->id));
    }

    public function test_purgar_cuenta_no_verificada(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(15),
        ]);

        app(AccountDeletionService::class)->purgeUnverified($user);

        $this->assertNull(User::find($user->id));
    }

    // -----------------------------------------------------------------------
    // Digest excluye cuentas suspendidas
    // -----------------------------------------------------------------------

    public function test_digest_no_se_envia_a_miembro_suspendido(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar X');
        $household->update(['reminders_enabled' => true]);

        // Recordatorio vencido para generar urgentes.
        $household->reminders()->create([
            'title' => 'Pago',
            'amount' => 100000,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $suspended = User::factory()->create();
        $suspended->deletion_requested_at = now()->subDays(5);
        $suspended->save();
        $household->members()->attach($suspended->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
            'reminders_email' => true,
        ]);

        $this->artisan('finlia:send-reminder-digests')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
