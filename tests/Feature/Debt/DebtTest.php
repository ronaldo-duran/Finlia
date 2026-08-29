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
use App\Services\DebtService;
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
            'planned_payment' => 800000,
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
            'planned_payment' => 100000,
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
            // El aviso de aproximación ya no se esconde dentro de la proyección:
            // vive en su propio bloque, visible sobre las cifras.
            ->assertSee('Los valores son aproximados');
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
            'planned_payment' => 300000,
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
            'planned_payment' => 90000,
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

    // ===== Plazo en cuotas y compromiso de pago (ADR-0022) =====

    public function test_el_fin_previsto_se_calcula_desde_inicio_mas_cuotas(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Crédito moto',
            'type' => DebtType::Vehicle->value,
            'original_amount' => 6000000,
            'start_date' => '2026-01-31',
            'term_months' => 24,
        ])->assertRedirect();

        // 31/01 + 24 meses debe caer el 31/01, no desbordar a marzo.
        $this->assertSame('2028-01-31', Debt::firstWhere('name', 'Crédito moto')->end_date->toDateString());
    }

    public function test_sin_fecha_de_inicio_no_hay_fin_previsto(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Sin inicio',
            'type' => DebtType::Loan->value,
            'original_amount' => 1000000,
            'term_months' => 12,
        ])->assertRedirect();

        $this->assertNull(Debt::firstWhere('name', 'Sin inicio')->end_date);
    }

    public function test_cada_tipo_de_deuda_tiene_su_tope_de_cuotas(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Una tarjeta a 240 cuotas no tiene sentido: el tope es 100.
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Tarjeta larga',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 1000000,
            'term_months' => 240,
        ])->assertSessionHasErrors('term_months');

        // Ese mismo plazo sí es normal en un hipotecario (tope 480).
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Casa',
            'type' => DebtType::Mortgage->value,
            'original_amount' => 300000000,
            'term_months' => 240,
        ])->assertSessionDoesntHaveErrors('term_months');

        $this->assertDatabaseHas('debts', ['name' => 'Casa', 'type' => 'mortgage', 'term_months' => 240]);
    }

    public function test_un_tipo_manipulado_no_rompe_la_validacion(): void
    {
        [$owner] = $this->setupHousehold();

        // type[]=x haría que input() devuelva un array.
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Manipulada',
            'type' => ['credit_card'],
            'original_amount' => 1000000,
            'term_months' => 12,
        ])->assertSessionHasErrors('type');
    }

    public function test_no_se_puede_planear_pagar_menos_que_el_minimo(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Incoherente',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 1000000,
            'minimum_payment' => 100000,
            'planned_payment' => 50000, // menos que el mínimo exigido
        ])->assertSessionHasErrors('planned_payment');
    }

    public function test_sin_plan_propio_el_compromiso_es_la_cuota_minima(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $debt = $this->debtFor($household, [
            'minimum_payment' => 120000,
            'planned_payment' => null,
            'current_balance' => 1000000,
        ]);

        $this->assertSame(120000.0, $debt->monthlyCommitment());
        $this->assertFalse($debt->paysAboveMinimum());
    }

    public function test_si_planeas_pagar_de_mas_manda_tu_plan(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $debt = $this->debtFor($household, [
            'minimum_payment' => 120000,
            'planned_payment' => 400000,
            'current_balance' => 1000000,
        ]);

        // Lo que sale del bolsillo es el plan, no el mínimo.
        $this->assertSame(400000.0, $debt->monthlyCommitment());
        $this->assertTrue($debt->paysAboveMinimum());
    }

    // ===== Simulador y coherencia (ADR-0023) =====

    public function test_el_alta_vive_en_su_propia_pantalla_y_el_panel_solo_tiene_el_boton(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // El panel ya no lleva el formulario incrustado, solo el acceso.
        $panel = $this->actingAs($owner)->get(route('debts.index'))->assertOk();
        $panel->assertSee(route('debts.create'), false);
        $panel->assertDontSee('name="original_amount"', false);

        // Y el formulario está en su pantalla.
        $this->actingAs($owner)->get(route('debts.create'))
            ->assertOk()
            ->assertSee('name="original_amount"', false)
            ->assertSee('Lo que pactaste');
    }

    public function test_la_cuota_se_calcula_sola_si_no_la_escribes(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Sin JavaScript el campo llega vacío: lo deriva el servidor.
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Sin cuota escrita',
            'type' => DebtType::Loan->value,
            'original_amount' => 10000000,
            'interest_rate' => 0,
            'term_months' => 120,
        ])->assertRedirect();

        $debt = Debt::firstWhere('name', 'Sin cuota escrita');
        $this->assertSame('83333.34', $debt->minimum_payment);
    }

    public function test_si_escribes_la_cuota_se_respeta(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Una entidad puede cobrar seguros encima de la cuota teórica.
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Con seguro',
            'type' => DebtType::Loan->value,
            'original_amount' => 10000000,
            'interest_rate' => 0,
            'term_months' => 120,
            'minimum_payment' => 90000,
        ])->assertRedirect();

        $this->assertSame('90000.00', Debt::firstWhere('name', 'Con seguro')->minimum_payment);
    }

    /**
     * El caso exacto que se reportó: 10.000.000 en 120 cuotas pagando 20.000
     * al mes. La aplicación lo aceptaba y luego calculaba 500 meses.
     */
    public function test_no_se_puede_registrar_una_deuda_imposible(): void
    {
        [$owner] = $this->setupHousehold();

        $response = $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Imposible',
            'type' => DebtType::Loan->value,
            'original_amount' => 10000000,
            'interest_rate' => 0,
            'term_months' => 120,
            'minimum_payment' => 20000,
        ]);

        $response->assertSessionHasErrors('minimum_payment');
        $this->assertDatabaseMissing('debts', ['name' => 'Imposible']);

        // El mensaje dice cuánto haría falta, no solo que está mal.
        $this->assertStringContainsString(
            '83.333',
            session('errors')->first('minimum_payment'),
        );
    }

    public function test_una_cuota_que_no_cubre_los_intereses_se_rechaza(): void
    {
        [$owner] = $this->setupHousehold();

        // 24 % E.A. sobre 1.000.000 son ~18.100 al mes solo de intereses.
        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Nunca baja',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 1000000,
            'interest_rate' => 24,
            'term_months' => 60,
            'minimum_payment' => 15000,
        ])->assertSessionHasErrors('minimum_payment');
    }

    public function test_una_cuota_coherente_se_acepta(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Coherente',
            'type' => DebtType::Vehicle->value,
            'original_amount' => 9000000,
            'interest_rate' => 16,
            'term_months' => 48,
            'minimum_payment' => 250176.49,
            'start_date' => '2026-01-15',
        ])->assertSessionDoesntHaveErrors();

        $debt = Debt::firstWhere('name', 'Coherente');
        $this->assertSame('2030-01-15', $debt->end_date->toDateString());
    }

    public function test_la_proyeccion_coincide_con_el_plazo_pactado(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('debts.store'), [
            'name' => 'Cuadrada',
            'type' => DebtType::Loan->value,
            'original_amount' => 12000000,
            'interest_rate' => 28.5,
            'term_months' => 36,
        ])->assertRedirect();

        $debt = Debt::firstWhere('name', 'Cuadrada');

        // Si el simulador dice 36 cuotas, la proyección no puede decir otra cosa.
        $this->assertSame(36, app(DebtService::class)->projectPayoff($debt)['months']);
    }

    public function test_el_aviso_de_que_los_valores_son_aproximados_se_ve_en_las_tres_pantallas(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $debt = $this->debtFor($household, ['current_balance' => 1000000, 'planned_payment' => 100000]);

        $pantallas = [
            'alta' => route('debts.create'),
            'panel' => route('debts.index'),
            'detalle' => route('debts.show', $debt),
        ];

        foreach ($pantallas as $nombre => $url) {
            $this->actingAs($owner)->get($url)
                ->assertOk()
                ->assertSee('Los valores son aproximados')
                ->assertSee('Tu entidad puede aplicar otras reglas', false);
        }
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

        // El nombre debe viajar como DATO (atributo data-confirm), nunca
        // dentro de un manejador en línea: ahí el navegador decodifica las
        // entidades antes de compilar el JS y la comilla cerraría el literal.
        $this->assertMatchesRegularExpression('/data-confirm="[^"]*Eliminar la deuda/', $html);

        // Ningún manejador en línea puede llevar datos del usuario.
        preg_match_all('/\son(?:submit|click)="([^"]*)"/', $html, $m);
        foreach ($m[1] as $handler) {
            $this->assertStringNotContainsString(
                'alert(1)',
                html_entity_decode($handler, ENT_QUOTES, 'UTF-8'),
                'El nombre de la deuda acabó dentro de un manejador JavaScript en línea.',
            );
        }
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
