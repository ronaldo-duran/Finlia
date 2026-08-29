<?php

namespace Tests\Feature;

use App\Enums\AcknowledgementKey;
use App\Enums\HouseholdRole;
use App\Models\Debt;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avisos dados por leídos (ADR-0024).
 */
class AcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private function setupConDeuda(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $debt = Debt::factory()->create([
            'household_id' => $household->id,
            'current_balance' => 1000000,
            'planned_payment' => 100000,
        ]);

        return [$owner, $debt];
    }

    public function test_guest_no_puede_marcar_avisos(): void
    {
        $this->post(route('acknowledgements.store', 'debt_estimates'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_acknowledgements', 0);
    }

    public function test_una_clave_inventada_no_crea_filas(): void
    {
        [$owner] = $this->setupConDeuda();

        // La clave llega por URL: sin lista cerrada, cualquiera podría llenar
        // la tabla de basura.
        $this->actingAs($owner)
            ->post(route('acknowledgements.store', 'lo-que-sea'))
            ->assertNotFound();

        $this->assertDatabaseCount('user_acknowledgements', 0);
    }

    public function test_marcar_el_aviso_lo_reduce_a_una_linea(): void
    {
        [$owner, $debt] = $this->setupConDeuda();

        // Antes: bloque completo con su botón.
        $this->actingAs($owner)->get(route('debts.index'))
            ->assertSee('Los valores son aproximados')
            ->assertSee('Entendido, no mostrar de nuevo');

        $this->actingAs($owner)
            ->post(route('acknowledgements.store', AcknowledgementKey::DebtEstimates->value))
            ->assertRedirect();

        // Después: el aviso sigue, pero en una línea y sin botón.
        $panel = $this->actingAs($owner)->get(route('debts.index'))->assertOk();
        $panel->assertDontSee('Entendido, no mostrar de nuevo');
        $panel->assertSee('Los valores son aproximados y pueden variar según tu entidad.');
    }

    public function test_el_aviso_nunca_desaparece_del_todo(): void
    {
        [$owner, $debt] = $this->setupConDeuda();
        $owner->acknowledge(AcknowledgementKey::DebtEstimates);

        // En una app de finanzas la advertencia tiene que seguir junto a las
        // cifras, en las tres pantallas.
        foreach ([route('debts.create'), route('debts.index'), route('debts.show', $debt)] as $url) {
            $this->actingAs($owner)->get($url)
                ->assertOk()
                ->assertSee('Los valores son aproximados');
        }
    }

    public function test_marcarlo_dos_veces_no_duplica_ni_mueve_la_fecha(): void
    {
        [$owner] = $this->setupConDeuda();

        $owner->acknowledge(AcknowledgementKey::DebtEstimates);
        $primera = $owner->acknowledgements()->first()->acknowledged_at;

        $owner->acknowledge(AcknowledgementKey::DebtEstimates);

        $this->assertDatabaseCount('user_acknowledgements', 1);
        $this->assertEquals($primera, $owner->acknowledgements()->first()->acknowledged_at);
    }

    public function test_el_acuse_es_de_cada_usuario_no_del_hogar(): void
    {
        [$owner, $debt] = $this->setupConDeuda();
        $owner->acknowledge(AcknowledgementKey::DebtEstimates);

        // Otro miembro del mismo hogar lo lee por su cuenta.
        $otro = User::factory()->create();
        $debt->household->members()->attach($otro->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);

        $this->assertTrue($owner->fresh()->hasAcknowledged(AcknowledgementKey::DebtEstimates));
        $this->assertFalse($otro->hasAcknowledged(AcknowledgementKey::DebtEstimates));
    }

    public function test_no_se_puede_marcar_el_aviso_de_otro_usuario(): void
    {
        [$owner] = $this->setupConDeuda();
        $otro = User::factory()->create();

        // El id sale del usuario autenticado, no de la petición: aunque se
        // envíe user_id, se ignora.
        $this->actingAs($owner)->post(
            route('acknowledgements.store', AcknowledgementKey::DebtEstimates->value),
            ['user_id' => $otro->id],
        )->assertRedirect();

        $this->assertDatabaseHas('user_acknowledgements', ['user_id' => $owner->id]);
        $this->assertDatabaseMissing('user_acknowledgements', ['user_id' => $otro->id]);
    }
}
