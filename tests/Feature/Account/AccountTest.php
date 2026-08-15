<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');

        return [$owner, $household];
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
    }

    public function test_usuario_puede_listar_sus_cuentas(): void
    {
        [$owner, $household] = $this->setupHousehold();
        Account::factory()->create(['name' => 'Davivienda', 'household_id' => $household->id]);

        $this->actingAs($owner)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('Davivienda');
    }

    public function test_usuario_puede_crear_una_cuenta_y_el_saldo_inicial_es_correcto(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('accounts.store'), [
            'name' => 'Efectivo',
            'type' => 'cash',
            'initial_balance' => 150000,
            'currency' => 'COP',
        ])->assertRedirect(route('accounts.index'));

        $account = Account::where('name', 'Efectivo')->firstOrFail();

        $this->assertSame('150000.00', (string) $account->initial_balance);
        // current_balance = initial + 0 movimientos (ADR-0012).
        $this->assertSame('150000.00', (string) $account->current_balance);
    }

    public function test_creacion_valida_campos_obligatorios(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('accounts.store'), [
            'name' => '',
            'type' => 'cash',
            'initial_balance' => 0,
            'currency' => 'COP',
        ])->assertSessionHasErrors('name');
    }

    public function test_usuario_puede_actualizar_una_cuenta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create(['household_id' => $household->id, 'name' => 'Vieja']);

        $this->actingAs($owner)->put(route('accounts.update', $account), [
            'name' => 'Renombrada',
            'type' => 'bank',
            'initial_balance' => 0,
            'currency' => 'COP',
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame('Renombrada', $account->fresh()->name);
    }

    public function test_no_se_puede_eliminar_una_cuenta_con_movimientos(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create(['household_id' => $household->id]);
        $account->expenses()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'date' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($owner)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_usuario_puede_eliminar_una_cuenta_sin_movimientos(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create(['household_id' => $household->id]);

        $this->actingAs($owner)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    // ===== Aislamiento multi-hogar (amenaza #1 — IDOR) =====

    public function test_usuario_ajeno_no_puede_ver_cuenta_de_otro_hogar(): void
    {
        [, , $account] = $this->setupWithAccount();
        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)
            ->get(route('accounts.show', $account))
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_editar_cuenta_de_otro_hogar(): void
    {
        [, , $account] = $this->setupWithAccount();
        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)->put(route('accounts.update', $account), [
            'name' => 'Hack',
            'type' => 'cash',
            'initial_balance' => 0,
            'currency' => 'COP',
        ])->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_eliminar_cuenta_de_otro_hogar(): void
    {
        [, , $account] = $this->setupWithAccount();
        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)
            ->delete(route('accounts.destroy', $account))
            ->assertForbidden();
    }

    private function setupWithAccount(): array
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create(['household_id' => $household->id]);

        return [$owner, $household, $account];
    }
}
