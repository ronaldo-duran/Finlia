<?php

namespace Tests\Feature\Reminder;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Baja del digest desde el propio correo (Épica 9, ADR-0028): URL firmada
 * por usuario+hogar, válida sin sesión. GET confirma; POST es el one-click
 * de RFC 8058 que Gmail/Yahoo disparan desde su botón nativo.
 */
class ReminderUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar A');
        $this->household->members()->updateExistingPivot($this->owner->id, [
            'reminders_email' => true,
        ]);
    }

    private function bajaUrl(): string
    {
        return URL::temporarySignedRoute('reminders.unsubscribe', now()->addDays(60), [
            'user' => $this->owner->id,
            'household' => $this->household->id,
        ]);
    }

    public function test_el_click_sin_sesion_da_de_baja_y_confirma(): void
    {
        $this->get($this->bajaUrl())
            ->assertOk()
            ->assertSee('ya no te enviamos el resumen');

        $this->assertDatabaseHas('household_user', [
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'reminders_email' => false,
        ]);
    }

    public function test_la_baja_es_por_hogar_y_no_toca_la_de_otros(): void
    {
        // La firma lleva el hogar: darse de baja del digest de A no apaga
        // el que el mismo usuario tenga activo en B.
        $hogarB = app(HouseholdService::class)->createHousehold($this->owner->id, 'Hogar B');
        $hogarB->members()->updateExistingPivot($this->owner->id, [
            'reminders_email' => true,
        ]);

        $this->get($this->bajaUrl())->assertOk();

        $this->assertDatabaseHas('household_user', [
            'household_id' => $hogarB->id,
            'user_id' => $this->owner->id,
            'reminders_email' => true,
        ]);
    }

    public function test_firma_invalida_rechazada(): void
    {
        $url = str_replace('signature=', 'signature=falsa', $this->bajaUrl());

        $this->get($url)->assertForbidden();

        // La preferencia queda intacta.
        $this->assertDatabaseHas('household_user', [
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'reminders_email' => true,
        ]);
    }

    public function test_post_one_click_da_de_baja_y_devuelve_204(): void
    {
        $this->post($this->bajaUrl())->assertNoContent();

        $this->assertDatabaseHas('household_user', [
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'reminders_email' => false,
        ]);
    }

    public function test_es_idempotente_un_segundo_click_no_errora(): void
    {
        $url = $this->bajaUrl();

        $this->get($url)->assertOk();
        // La firma no se consume con el uso: clic dos veces, mismo resultado.
        $this->get($url)->assertOk();
    }
}
