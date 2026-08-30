<?php

namespace Tests\Feature\Reminder;

use App\Enums\Frequency;
use App\Enums\ReminderStatus;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recordatorios sueltos (Épica 9, ADR-0027): CRUD, atender, interruptor
 * del hogar y aislamiento multi-hogar.
 */
class ReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Household}
     */
    private function setupHousehold(string $name = 'Hogar A'): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, $name);

        return [$owner, $household];
    }

    // ===== Acceso y CRUD =====

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('reminders.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_ve_sus_recordatorios_y_las_fuentes_derivadas(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $household->reminders()->create([
            'title' => 'Tecnomecánica', 'amount' => 250000,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
        $household->recurringExpenses()->create([
            'name' => 'Arriendo', 'amount' => 1200000,
            'frequency' => Frequency::Monthly->value,
            'next_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertSee('Tecnomecánica')
            ->assertSee('Arriendo')
            ->assertSee('Gasto recurrente');
    }

    public function test_usuario_puede_crear_un_recordatorio(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('reminders.store'), [
            'title' => 'Renovar pasaporte',
            'amount' => 180000,
            'due_date' => '2026-11-20',
            'frequency' => 'yearly',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('reminders', [
            'household_id' => $household->id,
            'title' => 'Renovar pasaporte',
            'amount' => '180000.00',
            'due_date' => '2026-11-20',
            'frequency' => 'yearly',
            'status' => 'pending',
        ]);
    }

    public function test_campos_obligatorios_y_frecuencia_valida(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('reminders.store'), [
            'title' => '',
            'due_date' => '',
            // Semanal no es una repetición válida para un aviso suelto:
            // eso es un gasto recurrente de la Épica 5.
            'frequency' => 'weekly',
        ])->assertSessionHasErrors(['title', 'due_date', 'frequency']);
    }

    public function test_usuario_puede_actualizar_un_recordatorio(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $reminder = $household->reminders()->create([
            'title' => 'Tecnomecánica', 'due_date' => '2026-11-20',
        ]);

        $this->actingAs($owner)->put(route('reminders.update', $reminder), [
            'title' => 'Tecnomecánica 2027',
            'due_date' => '2026-11-25',
            'frequency' => 'yearly',
        ])->assertRedirect(route('reminders.index'));

        $fresh = $reminder->fresh();
        $this->assertSame('Tecnomecánica 2027', $fresh->title);
        $this->assertSame('2026-11-25', $fresh->due_date->toDateString());
        $this->assertSame(Frequency::Yearly, $fresh->frequency);
    }

    public function test_usuario_puede_eliminar_un_recordatorio(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $reminder = $household->reminders()->create([
            'title' => 'Tecnomecánica', 'due_date' => '2026-11-20',
        ]);

        $this->actingAs($owner)
            ->delete(route('reminders.destroy', $reminder))
            ->assertRedirect(route('reminders.index'));

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }

    // ===== Atender =====

    public function test_atender_un_suelto_lo_completa_y_no_genera_gasto(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $reminder = $household->reminders()->create([
            'title' => 'Renovar pasaporte', 'due_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->post(route('reminders.complete', $reminder))
            ->assertRedirect(route('reminders.index'));

        // El cast del modelo devuelve el enum, no el string crudo.
        $this->assertSame(ReminderStatus::Completed, $reminder->fresh()->status);
        // Un recordatorio es un aviso, no un movimiento (ADR-0027).
        $this->assertSame(0, $household->expenses()->count());
    }

    public function test_atender_un_anual_avanza_la_fecha(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $reminder = $household->reminders()->create([
            'title' => 'SOAT', 'amount' => 600000,
            'due_date' => '2026-09-20', 'frequency' => Frequency::Yearly->value,
        ]);

        $this->actingAs($owner)
            ->post(route('reminders.complete', $reminder))
            ->assertRedirect(route('reminders.index'));

        $this->assertSame('2027-09-20', $reminder->fresh()->due_date->toDateString());
        $this->assertSame(ReminderStatus::Pending, $reminder->fresh()->status);
    }

    // ===== Interruptor del hogar =====

    public function test_el_administrador_puede_desactivar_los_recordatorios(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->put(route('reminders.settings'), [
            'reminders_enabled' => '0',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'reminders_enabled' => false,
        ]);

        // Con el interruptor apagado, la página no lista obligaciones.
        $this->actingAs($owner)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertSee('desactivados');
    }

    public function test_un_miembro_que_no_es_administrador_no_puede_cambiar_el_interruptor(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $member = User::factory()->create();
        $household->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member)
            ->withSession(['household_id' => $household->id])
            ->put(route('reminders.settings'), ['reminders_enabled' => '0'])
            ->assertForbidden();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'reminders_enabled' => true,
        ]);
    }

    // ===== Preferencia de digest por correo (ADR-0028) =====

    public function test_miembro_puede_activar_y_desactivar_su_resumen_por_correo(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Opt-in: nace desactivado, cada quien lo prende.
        $this->actingAs($owner)->put(route('reminders.email'), [
            'reminders_email' => '1',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'reminders_email' => true,
        ]);

        $this->actingAs($owner)->put(route('reminders.email'), [
            'reminders_email' => '0',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('household_user', [
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'reminders_email' => false,
        ]);
    }

    public function test_la_preferencia_es_personal_y_no_toca_la_de_otros(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $member = User::factory()->create();
        $household->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($owner)->put(route('reminders.email'), [
            'reminders_email' => '1',
        ])->assertRedirect(route('reminders.index'));

        $this->assertDatabaseHas('household_user', [
            'user_id' => $owner->id,
            'reminders_email' => true,
        ]);
        $this->assertDatabaseHas('household_user', [
            'user_id' => $member->id,
            'reminders_email' => false,
        ]);
    }

    public function test_usuario_sin_hogar_es_llevado_a_crear_uno(): void
    {
        // Sin hogar no hay preferencia que tocar: mismo patrón que el
        // interruptor (redirect, no 403). La escritura siempre usa el pivote
        // del propio usuario en su hogar activo: no hay vector cruzado.
        $sinHogar = User::factory()->create();

        $this->actingAs($sinHogar)
            ->put(route('reminders.email'), ['reminders_email' => '1'])
            ->assertRedirect(route('households.create'));
    }

    // ===== Aislamiento multi-hogar =====

    public function test_usuario_de_otro_hogar_no_puede_operar_un_recordatorio_ajeno(): void
    {
        [$ownerA, $householdA] = $this->setupHousehold('Hogar A');
        [$ownerB] = $this->setupHousehold('Hogar B');

        $reminder = $householdA->reminders()->create([
            'title' => 'Secreto de A', 'due_date' => '2026-11-20',
        ]);

        $this->actingAs($ownerB)
            ->put(route('reminders.update', $reminder), [
                'title' => 'Hackeado', 'due_date' => '2026-11-21',
            ])->assertForbidden();

        $this->actingAs($ownerB)
            ->delete(route('reminders.destroy', $reminder))
            ->assertForbidden();

        $this->actingAs($ownerB)
            ->post(route('reminders.complete', $reminder))
            ->assertForbidden();

        $this->assertSame('Secreto de A', $reminder->fresh()->title);
    }

    public function test_miembro_de_dos_hogares_con_el_otro_activo_recibe_403(): void
    {
        // El caso de ADR-0019: es miembro de ambos, pero el hogar ACTIVO
        // decide sobre cuál puede operar.
        $user = User::factory()->create();
        $service = app(HouseholdService::class);
        $hogarA = $service->createHousehold($user->id, 'Hogar A');
        $hogarB = $service->createHousehold($user->id, 'Hogar B');

        $reminder = $hogarA->reminders()->create([
            'title' => 'De A', 'due_date' => '2026-11-20',
        ]);

        // Hogar B activo: el recordatorio de A no se puede tocar.
        $this->withSession(['household_id' => $hogarB->id])
            ->actingAs($user)
            ->put(route('reminders.update', $reminder), [
                'title' => 'Hackeado', 'due_date' => '2026-11-21',
            ])
            ->assertForbidden();

        // Con su hogar A activo, sí. active_household() cachea el hogar por
        // petición en el contenedor y en tests la app persiste entre
        // peticiones: hay que olvidar la instancia para que re-resuelva.
        $this->app->forgetInstance('finlia.active_household');
        $this->withSession(['household_id' => $hogarA->id])
            ->actingAs($user)
            ->put(route('reminders.update', $reminder), [
                'title' => 'Editado desde A', 'due_date' => '2026-11-21',
            ])
            ->assertRedirect(route('reminders.index'));
    }
}
