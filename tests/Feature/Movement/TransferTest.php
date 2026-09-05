<?php

namespace Tests\Feature\Movement;

use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\MovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests de transferencias entre cuentas (Épica 10, ADR-0035).
 *
 * Cubre CRUD básico, recomputo de saldos y aislamiento multi-hogar.
 */
class TransferTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{User, Household, Account, Account}
     */
    private function setup2Accounts(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');
        $from = Account::factory()->withInitialBalance(200000)->create(['household_id' => $household->id]);
        $to = Account::factory()->withInitialBalance(0)->create(['household_id' => $household->id]);

        return [$owner, $household, $from, $to];
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('transfers.create'))->assertRedirect(route('login'));
    }

    public function test_puede_registrar_transferencia_y_saldos_se_ajustan(): void
    {
        [$owner, , $from, $to] = $this->setup2Accounts();

        $this->actingAs($owner)->post(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 50000,
            'date' => now()->format('Y-m-d'),
            'description' => 'Traslado prueba',
        ])->assertRedirect(route('movements.index'));

        $this->assertDatabaseHas('transfers', [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => '50000.00',
        ]);

        // El origen pierde y el destino gana.
        $this->assertSame('150000.00', (string) $from->fresh()->current_balance);
        $this->assertSame('50000.00', (string) $to->fresh()->current_balance);
    }

    public function test_valida_campos_obligatorios(): void
    {
        [$owner] = $this->setup2Accounts();

        $this->actingAs($owner)->post(route('transfers.store'), [
            'amount' => '',
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors(['from_account_id', 'to_account_id', 'amount']);
    }

    public function test_origen_y_destino_no_pueden_ser_la_misma_cuenta(): void
    {
        [$owner, , $from] = $this->setup2Accounts();

        $this->actingAs($owner)->post(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $from->id,
            'amount' => 1000,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('to_account_id');
    }

    public function test_rechaza_cuentas_de_otro_hogar(): void
    {
        [$owner, , $from, $to] = $this->setup2Accounts();

        $otro = User::factory()->create();
        $otroHogar = app(HouseholdService::class)->createHousehold($otro->id, 'Hogar B');
        $cuentaAjena = Account::factory()->create(['household_id' => $otroHogar->id]);

        $this->actingAs($owner)->post(route('transfers.store'), [
            'from_account_id' => $cuentaAjena->id,
            'to_account_id' => $to->id,
            'amount' => 1000,
            'date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('from_account_id');
    }

    public function test_puede_editar_transferencia_y_saldos_se_recomputan(): void
    {
        [$owner, $household, $from, $to] = $this->setup2Accounts();

        $transfer = app(MovementService::class)->createTransfer(
            data: ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 30000, 'date' => now()->format('Y-m-d')],
            household: $household,
            user: $owner,
        );

        // Saldos tras la transferencia inicial: from=170000, to=30000.
        $this->assertSame('170000.00', (string) $from->fresh()->current_balance);
        $this->assertSame('30000.00', (string) $to->fresh()->current_balance);

        // Editar: subir a 80000.
        $this->actingAs($owner)->put(route('transfers.update', $transfer), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 80000,
            'date' => now()->format('Y-m-d'),
        ])->assertRedirect(route('movements.index'));

        $this->assertSame('120000.00', (string) $from->fresh()->current_balance);
        $this->assertSame('80000.00', (string) $to->fresh()->current_balance);
    }

    public function test_puede_eliminar_transferencia_y_saldos_se_revierten(): void
    {
        [$owner, $household, $from, $to] = $this->setup2Accounts();

        $transfer = app(MovementService::class)->createTransfer(
            data: ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 50000, 'date' => now()->format('Y-m-d')],
            household: $household,
            user: $owner,
        );

        $this->actingAs($owner)
            ->delete(route('transfers.destroy', $transfer))
            ->assertRedirect(route('movements.index'));

        $this->assertDatabaseMissing('transfers', ['id' => $transfer->id]);
        $this->assertSame('200000.00', (string) $from->fresh()->current_balance);
        $this->assertSame('0.00', (string) $to->fresh()->current_balance);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_ver_transferencia_de_otro_hogar(): void
    {
        [$owner, $household, $from, $to] = $this->setup2Accounts();

        $transfer = app(MovementService::class)->createTransfer(
            data: ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 10000, 'date' => now()->format('Y-m-d')],
            household: $household,
            user: $owner,
        );

        $otro = User::factory()->create();
        app(HouseholdService::class)->createHousehold($otro->id, 'Hogar B');

        $this->actingAs($otro)
            ->get(route('transfers.edit', $transfer))
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_eliminar_transferencia_de_otro_hogar(): void
    {
        [$owner, $household, $from, $to] = $this->setup2Accounts();

        $transfer = app(MovementService::class)->createTransfer(
            data: ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 10000, 'date' => now()->format('Y-m-d')],
            household: $household,
            user: $owner,
        );

        $otro = User::factory()->create();
        app(HouseholdService::class)->createHousehold($otro->id, 'Hogar B');

        $this->actingAs($otro)
            ->delete(route('transfers.destroy', $transfer))
            ->assertForbidden();
    }

    public function test_transferencia_aparece_en_lista_de_movimientos(): void
    {
        [$owner, $household, $from, $to] = $this->setup2Accounts();

        app(MovementService::class)->createTransfer(
            data: ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 15000, 'date' => now()->format('Y-m-d'), 'description' => 'Mi traslado'],
            household: $household,
            user: $owner,
        );

        $this->actingAs($owner)
            ->get(route('movements.index'))
            ->assertOk()
            ->assertSee('Mi traslado');
    }

    public function test_no_hay_columnas_sensibles_de_tarjeta_en_la_tabla_transfers(): void
    {
        // La tabla transfers NUNCA debe tener columnas de datos sensibles de
        // tarjeta (número completo, CVV, PIN) — ADR-0002 y SECURITY.md.
        $columns = Schema::getColumnListing('transfers');

        $forbidden = ['card_number', 'cvv', 'pin', 'full_pan'];
        foreach ($forbidden as $col) {
            $this->assertNotContains($col, $columns, "La tabla transfers no debe tener la columna: {$col}");
        }
    }
}
