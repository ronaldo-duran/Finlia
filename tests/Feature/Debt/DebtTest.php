<?php

namespace Tests\Feature\Debt;

use App\Enums\AccountType;
use App\Enums\DebtPaymentType;
use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Épica 6: CRUD de deudas, pagos, refinanciación, tarjetas y —sobre todo—
 * aislamiento entre hogares (amenaza #1 de docs/SECURITY.md).
 */
class DebtTest extends TestCase
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

    private function debtFor(Household $household, array $attributes = []): Debt
    {
        return Debt::factory()->create(['household_id' => $household->id, ...$attributes]);
    }

    // ===== Acceso =====

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('debts.index'))->assertRedirect(route('login'));
    }

    public function test_el_panel_muestra_deuda_total_y_pago_mensual(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->debtFor($household, [
            'name' => 'Tarjeta Ahorros',
            'original_amount' => 4800000,
            'current_balance' => 4000000,
            'scheduled_payment' => 800000,
        ]);

        $this->actingAs($owner)
            ->get(route('debts.index'))
            ->assertOk()
            ->assertSee('Tarjeta Ahorros')
            ->assertSee('4.000.000,00')  // deuda total
            ->assertSee('800.000,00');   // pago mensual comprometido
    }

    public function test_el_detalle_muestra_historial_y_proyeccion(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household, [
            'name' => 'Crédito moto',
            'original_amount' => 1000000,
            'current_balance' => 1000000,
            'interest_rate' => 0,
            'scheduled_payment' => 100000,
            'minimum_payment' => null,
        ]);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 200000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Extra->value,
        ]);

        $this->actingAs($owner)
            ->get(route('debts.show', $debt))
            ->assertOk()
            ->assertSee('Crédito moto')
            ->assertSee('800.000,00')            // saldo tras el abono
            ->assertSee('Abono extra')           // historial
            ->assertSee('Es una estimación', false); // la proyección se marca como tal
    }

    public function test_el_detalle_de_una_cuenta_tarjeta_muestra_el_bloque_de_tarjeta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'type' => AccountType::CreditCard->value,
        ]);

        $this->actingAs($owner)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertSee('Datos de la tarjeta')
            // El aviso de seguridad es parte del producto, no decoración.
            ->assertSee('nunca te pedirá el número completo', false);
    }

    public function test_una_estrategia_manipulada_no_rompe_el_panel(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->debtFor($household);

        foreach (['?estrategia[]=x', '?estrategia=inventada', '?estrategia='] as $query) {
            $this->actingAs($owner)
                ->get(route('debts.index').$query)
                ->assertOk(); // cae a la estrategia por defecto, no a un 500
        }
    }

    // ===== CRUD =====

    public function test_usuario_puede_registrar_una_deuda(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Préstamo moto',
            'type' => DebtType::Vehicle->value,
            'original_amount' => 6000000,
            'scheduled_payment' => 300000,
            'due_day' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('debts', [
            'household_id' => $household->id,
            'name' => 'Préstamo moto',
            'original_amount' => 6000000.00,
            // El saldo arranca en la línea base, no lo teclea el usuario.
            'current_balance' => 6000000.00,
            'status' => DebtStatus::Active->value,
        ]);
    }

    public function test_el_saldo_no_se_puede_teclear_al_crear(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Intento de mass assignment sobre un campo derivado (ADR-0020).
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Intento',
            'type' => DebtType::Loan->value,
            'original_amount' => 1000000,
            'current_balance' => 1, // debe ignorarse
        ]);

        $this->assertSame('1000000.00', Debt::firstWhere('name', 'Intento')->current_balance);
    }

    public function test_usuario_puede_editar_una_deuda(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household, ['name' => 'Antigua', 'original_amount' => 1000000, 'current_balance' => 1000000]);

        $this->actingAs($owner)->put(route('debts.update', $debt), [
            'name' => 'Renombrada',
            'type' => $debt->type->value,
            'original_amount' => 1000000,
            'scheduled_payment' => 90000,
        ])->assertRedirect(route('debts.show', $debt));

        $this->assertSame('Renombrada', $debt->fresh()->name);
    }

    public function test_usuario_puede_eliminar_una_deuda(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household);

        $this->actingAs($owner)
            ->delete(route('debts.destroy', $debt))
            ->assertRedirect(route('debts.index'));

        // Borrado lógico: el historial financiero se conserva.
        $this->assertSoftDeleted('debts', ['id' => $debt->id]);
    }

    public function test_validacion_rechaza_datos_invalidos(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'X',            // muy corto
            'type' => 'inventado',    // fuera del enum
            'original_amount' => 0,   // debe ser > 0
            'due_day' => 45,          // fuera de rango
        ])->assertSessionHasErrors(['name', 'type', 'original_amount', 'due_day']);
    }

    // ===== Pagos =====

    public function test_registrar_un_pago_baja_el_saldo(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household, ['original_amount' => 4800000, 'current_balance' => 4800000]);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 800000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
        ])->assertRedirect(route('debts.show', $debt));

        $this->assertSame('4000000.00', $debt->fresh()->current_balance);
    }

    public function test_un_pago_con_cuenta_registra_el_gasto_y_mueve_el_saldo(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 2000000,
            'current_balance' => 2000000,
        ]);
        $debt = $this->debtFor($household, ['original_amount' => 1000000, 'current_balance' => 1000000]);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 300000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
            'account_id' => $account->id,
        ])->assertRedirect();

        // Baja la deuda…
        $this->assertSame('700000.00', $debt->fresh()->current_balance);
        // …y baja el saldo real de la cuenta (ADR-0021).
        $this->assertSame('1700000.00', $account->fresh()->current_balance);
        $this->assertDatabaseHas('expenses', [
            'household_id' => $household->id,
            'account_id' => $account->id,
            'amount' => 300000.00,
        ]);
    }

    public function test_borrar_un_pago_deshace_el_gasto_y_devuelve_el_saldo(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 2000000,
            'current_balance' => 2000000,
        ]);
        $debt = $this->debtFor($household, ['original_amount' => 1000000, 'current_balance' => 1000000]);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 300000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Scheduled->value,
            'account_id' => $account->id,
        ]);

        $payment = DebtPayment::firstWhere('debt_id', $debt->id);

        $this->actingAs($owner)
            ->delete(route('debts.payments.destroy', [$debt, $payment]))
            ->assertRedirect();

        $this->assertSame('1000000.00', $debt->fresh()->current_balance);
        $this->assertSame('2000000.00', $account->fresh()->current_balance);
        // Los movimientos se borran de forma lógica (DATA_MODEL): la fila
        // queda como historial, pero ya no cuenta para el saldo.
        $this->assertSoftDeleted('expenses', ['amount' => 300000.00]);
    }

    public function test_no_se_aceptan_pagos_con_fecha_futura(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 1000,
            'date' => now()->addWeek()->toDateString(),
            'type' => DebtPaymentType::Extra->value,
        ])->assertSessionHasErrors('date');
    }

    public function test_no_se_puede_borrar_un_pago_de_otra_deuda(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debtA = $this->debtFor($household);
        $debtB = $this->debtFor($household);
        $payment = DebtPayment::factory()->forDebt($debtB)->create();

        // El pago es del hogar (pasa la policy), pero no de esta deuda.
        $this->actingAs($owner)
            ->delete(route('debts.payments.destroy', [$debtA, $payment]))
            ->assertNotFound();
    }

    // ===== Refinanciación =====

    public function test_registrar_una_refinanciacion_cambia_la_linea_base(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household, ['original_amount' => 1000000, 'current_balance' => 1000000]);

        $this->actingAs($owner)->post(route('debts.refinancings.store', $debt), [
            'refinanced_balance' => 850000,
            'interest_rate' => 15.5,
            'term_months' => 18,
            'installment' => 55000,
            'start_date' => now()->toDateString(),
        ])->assertRedirect(route('debts.show', $debt));

        $debt->refresh();
        $this->assertSame('850000.00', $debt->current_balance);
        $this->assertSame(DebtStatus::Refinanced, $debt->status);
        $this->assertDatabaseHas('debt_refinancings', [
            'debt_id' => $debt->id,
            'household_id' => $household->id,
            'refinanced_balance' => 850000.00,
        ]);
    }

    // ===== Tarjetas (ADR-0002) =====

    public function test_se_pueden_guardar_los_datos_de_una_tarjeta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'type' => AccountType::CreditCard->value,
        ]);

        $this->actingAs($owner)->put(route('accounts.credit-card.update', $account), [
            'credit_limit' => 5000000,
            'statement_date' => 15,
            'payment_due_date' => 5,
        ])->assertRedirect(route('accounts.show', $account));

        $this->assertDatabaseHas('credit_cards', [
            'account_id' => $account->id,
            'household_id' => $household->id,
            'credit_limit' => 5000000.00,
            'available_credit' => 5000000.00,
        ]);
    }

    public function test_una_cuenta_que_no_es_tarjeta_no_admite_datos_de_tarjeta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'type' => AccountType::Bank->value,
        ]);

        $this->actingAs($owner)
            ->put(route('accounts.credit-card.update', $account), ['credit_limit' => 100000])
            ->assertNotFound();
    }

    public function test_nunca_se_almacenan_datos_sensibles_de_la_tarjeta(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'type' => AccountType::CreditCard->value,
        ]);

        // Aunque lleguen en la petición, no existen columnas donde guardarlos.
        $this->actingAs($owner)->put(route('accounts.credit-card.update', $account), [
            'credit_limit' => 3000000,
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'pin' => '4321',
        ])->assertRedirect();

        $columns = Schema::getColumnListing('credit_cards');
        foreach (['card_number', 'cvv', 'pin', 'number'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "La tabla credit_cards no debe tener la columna $forbidden.");
        }
    }

    public function test_el_nombre_de_la_deuda_no_puede_escapar_del_javascript_en_linea(): void
    {
        [$owner, $household] = $this->setupHousehold();
        // Carga clásica de XSS en contexto JS: cierra el literal y ejecuta.
        $debt = $this->debtFor($household, ['name' => "x');alert(1);//"]);

        $html = $this->actingAs($owner)->get(route('debts.show', $debt))->getContent();

        // Solo importa el contexto JavaScript: en HTML (título, value del
        // formulario) `&#039;` es correcto y seguro.
        preg_match('/onsubmit="([^"]*)"/', $html, $m);
        $this->assertNotEmpty($m, 'No se encontró el manejador onsubmit.');
        $handler = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');

        // `{{ }}` escaparía la comilla como `&#039;`, pero el navegador
        // DECODIFICA las entidades antes de compilar el manejador, así que
        // volvería a ser `'` y cerraría el literal. Se comprueba sobre el
        // manejador ya decodificado, que es lo que el navegador ejecuta.
        $this->assertStringNotContainsString("');alert(1)", $handler);
        $this->assertStringContainsString('\u0027', $handler);
    }

    // ===== Aislamiento entre hogares (amenaza #1) =====

    public function test_usuario_no_ve_deudas_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $this->debtFor($otroHogar, ['name' => 'Deuda ajena']);

        $this->actingAs($owner)
            ->get(route('debts.index'))
            ->assertOk()
            ->assertDontSee('Deuda ajena');
    }

    public function test_usuario_no_puede_ver_una_deuda_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->debtFor($otroHogar);

        $this->actingAs($owner)->get(route('debts.show', $ajena))->assertForbidden();
    }

    public function test_usuario_no_puede_editar_una_deuda_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->debtFor($otroHogar, ['name' => 'Intacta']);

        $this->actingAs($owner)->put(route('debts.update', $ajena), [
            'name' => 'Secuestrada',
            'type' => DebtType::Loan->value,
            'original_amount' => 1,
        ])->assertForbidden();

        $this->assertSame('Intacta', $ajena->fresh()->name);
    }

    public function test_usuario_no_puede_eliminar_una_deuda_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->debtFor($otroHogar);

        $this->actingAs($owner)->delete(route('debts.destroy', $ajena))->assertForbidden();
        $this->assertDatabaseHas('debts', ['id' => $ajena->id, 'deleted_at' => null]);
    }

    public function test_usuario_no_puede_pagar_una_deuda_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->debtFor($otroHogar, ['original_amount' => 500000, 'current_balance' => 500000]);

        $this->actingAs($owner)->post(route('debts.payments.store', $ajena), [
            'amount' => 100000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Extra->value,
        ])->assertForbidden();

        $this->assertSame('500000.00', $ajena->fresh()->current_balance);
        $this->assertDatabaseCount('debt_payments', 0);
    }

    public function test_usuario_no_puede_refinanciar_una_deuda_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->debtFor($otroHogar);

        $this->actingAs($owner)->post(route('debts.refinancings.store', $ajena), [
            'refinanced_balance' => 1,
            'start_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseCount('debt_refinancings', 0);
    }

    public function test_no_se_puede_enlazar_una_cuenta_de_otro_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $cuentaAjena = Account::factory()->create(['household_id' => $otroHogar->id]);

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Intento',
            'type' => DebtType::Loan->value,
            'original_amount' => 100000,
            'account_id' => $cuentaAjena->id,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_no_se_puede_pagar_desde_una_cuenta_de_otro_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $debt = $this->debtFor($household);
        $cuentaAjena = Account::factory()->create(['household_id' => $otroHogar->id]);

        $this->actingAs($owner)->post(route('debts.payments.store', $debt), [
            'amount' => 10000,
            'date' => now()->toDateString(),
            'type' => DebtPaymentType::Extra->value,
            'account_id' => $cuentaAjena->id,
        ])->assertSessionHasErrors('account_id');
    }
}
