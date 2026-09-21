<?php

namespace Tests\Feature\Receivable;

use App\Enums\ReceivablePaymentType;
use App\Enums\ReceivableStatus;
use App\Models\Account;
use App\Models\Household;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Épica 15: CRUD de cuentas por cobrar, cobros, ingreso automático y —sobre
 * todo— aislamiento entre hogares (amenaza #1 de docs/SECURITY.md).
 */
class ReceivableTest extends TestCase
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

    private function receivableFor(Household $household, array $attributes = []): Receivable
    {
        return Receivable::factory()->create(['household_id' => $household->id, ...$attributes]);
    }

    // ===== Acceso =====

    public function test_guest_es_redirigido_al_login(): void
    {
        $this->get(route('receivables.index'))->assertRedirect(route('login'));
    }

    public function test_el_panel_muestra_total_pendiente(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $this->receivableFor($household, [
            'debtor_name' => 'Amigo cercano',
            'name' => 'Préstamo camioneta',
            'original_amount' => 800000,
            'current_balance' => 600000,
            'status' => ReceivableStatus::Partial->value,
        ]);

        $this->actingAs($owner)
            ->get(route('receivables.index'))
            ->assertOk()
            ->assertSee('Amigo cercano')
            ->assertSee('600.000,00');
    }

    // ===== CRUD =====

    public function test_usuario_puede_registrar_una_cuenta_por_cobrar(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('receivables.store'), [
            'debtor_name' => 'Cliente Empresa X',
            'name' => 'Trabajo facturado',
            'original_amount' => 850000,
            'due_date' => now()->addDays(15)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('receivables', [
            'household_id' => $household->id,
            'name' => 'Trabajo facturado',
            'debtor_name' => 'Cliente Empresa X',
            'original_amount' => 850000.00,
            // Saldo arranca en la línea base.
            'current_balance' => 850000.00,
            'status' => ReceivableStatus::Pending->value,
        ]);
    }

    public function test_el_saldo_no_se_puede_teclear_al_crear(): void
    {
        [$owner, $household] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('receivables.store'), [
            'debtor_name' => 'Prueba',
            'name' => 'Intento',
            'original_amount' => 500000,
            'current_balance' => 1,
        ]);

        $this->assertSame('500000.00', Receivable::firstWhere('name', 'Intento')->current_balance);
    }

    public function test_usuario_puede_editar_una_cuenta_por_cobrar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $r = $this->receivableFor($household, [
            'debtor_name' => 'Nombre original',
            'name' => 'Antigua',
            'original_amount' => 400000,
            'current_balance' => 400000,
        ]);

        $this->actingAs($owner)->put(route('receivables.update', $r), [
            'debtor_name' => 'Nombre nuevo',
            'name' => 'Renombrada',
            'original_amount' => 400000,
        ])->assertRedirect(route('receivables.show', $r));

        $this->assertSame('Renombrada', $r->fresh()->name);
        $this->assertSame('Nombre nuevo', $r->fresh()->debtor_name);
    }

    public function test_usuario_puede_eliminar_una_cuenta_por_cobrar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $r = $this->receivableFor($household);

        $this->actingAs($owner)
            ->delete(route('receivables.destroy', $r))
            ->assertRedirect(route('receivables.index'));

        $this->assertSoftDeleted('receivables', ['id' => $r->id]);
    }

    public function test_validacion_rechaza_datos_invalidos(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('receivables.store'), [
            'debtor_name' => 'X',    // muy corto
            'name' => '',            // requerido
            'original_amount' => 0,  // > 0
        ])->assertSessionHasErrors(['debtor_name', 'name', 'original_amount']);
    }

    // ===== Cobros =====

    public function test_un_cobro_recibido_con_cuenta_genera_ingreso_y_sube_su_saldo(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $r = $this->receivableFor($household, [
            'original_amount' => 500000,
            'current_balance' => 500000,
        ]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 200000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
            'account_id' => $account->id,
        ])->assertRedirect();

        $r->refresh();
        $this->assertSame('300000.00', $r->current_balance);
        $this->assertSame(ReceivableStatus::Partial, $r->status);
        $this->assertSame('1200000.00', $account->fresh()->current_balance);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_una_condonacion_baja_el_saldo_sin_ingreso(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 100000,
            'current_balance' => 100000,
        ]);
        $r = $this->receivableFor($household, [
            'original_amount' => 300000,
            'current_balance' => 300000,
        ]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 100000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Forgiven->value,
            // Aunque envíe cuenta, la condonación NO genera ingreso.
            'account_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame('200000.00', $r->fresh()->current_balance);
        // El saldo de la cuenta se mantiene: no se creó ingreso.
        $this->assertSame('100000.00', $account->fresh()->current_balance);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_saldo_a_cero_pasa_el_estado_a_cobrado(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $r = $this->receivableFor($household, [
            'original_amount' => 200000,
            'current_balance' => 200000,
        ]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 200000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
        ]);

        $r->refresh();
        $this->assertSame('0.00', $r->current_balance);
        $this->assertSame(ReceivableStatus::Paid, $r->status);
    }

    public function test_borrar_un_cobro_deshace_el_ingreso_y_devuelve_el_saldo(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $r = $this->receivableFor($household, [
            'original_amount' => 500000,
            'current_balance' => 500000,
        ]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 200000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
            'account_id' => $account->id,
        ]);

        $payment = ReceivablePayment::firstWhere('receivable_id', $r->id);

        $this->actingAs($owner)
            ->delete(route('receivables.payments.destroy', [$r, $payment]))
            ->assertRedirect();

        $this->assertSame('500000.00', $r->fresh()->current_balance);
        $this->assertSame('1000000.00', $account->fresh()->current_balance);
        $this->assertSoftDeleted('incomes', ['amount' => 200000.00]);
    }

    public function test_no_se_aceptan_cobros_con_fecha_futura(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $r = $this->receivableFor($household);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 1000,
            'date' => now()->addWeek()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
        ])->assertSessionHasErrors('date');
    }

    public function test_no_se_puede_borrar_un_cobro_de_otra_cuenta_por_cobrar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $rA = $this->receivableFor($household);
        $rB = $this->receivableFor($household);
        $payment = ReceivablePayment::factory()->forReceivable($rB)->create();

        $this->actingAs($owner)
            ->delete(route('receivables.payments.destroy', [$rA, $payment]))
            ->assertNotFound();
    }

    // ===== Aislamiento entre hogares (amenaza #1) =====

    public function test_usuario_no_ve_cuentas_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $this->receivableFor($otroHogar, ['debtor_name' => 'Deudor Ajeno', 'name' => 'Cuenta Ajena']);

        $this->actingAs($owner)
            ->get(route('receivables.index'))
            ->assertOk()
            ->assertDontSee('Deudor Ajeno')
            ->assertDontSee('Cuenta Ajena');
    }

    public function test_usuario_no_puede_ver_una_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->receivableFor($otroHogar);

        $this->actingAs($owner)->get(route('receivables.show', $ajena))->assertForbidden();
    }

    public function test_usuario_no_puede_editar_una_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->receivableFor($otroHogar, ['name' => 'Intacta']);

        $this->actingAs($owner)->put(route('receivables.update', $ajena), [
            'debtor_name' => 'Nuevo',
            'name' => 'Secuestrada',
            'original_amount' => 1,
        ])->assertForbidden();

        $this->assertSame('Intacta', $ajena->fresh()->name);
    }

    public function test_usuario_no_puede_eliminar_una_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->receivableFor($otroHogar);

        $this->actingAs($owner)->delete(route('receivables.destroy', $ajena))->assertForbidden();
        $this->assertDatabaseHas('receivables', ['id' => $ajena->id, 'deleted_at' => null]);
    }

    public function test_usuario_no_puede_cobrar_una_cuenta_de_otro_hogar(): void
    {
        [$owner] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $ajena = $this->receivableFor($otroHogar, [
            'original_amount' => 500000,
            'current_balance' => 500000,
        ]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $ajena), [
            'amount' => 100000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
        ])->assertForbidden();

        $this->assertSame('500000.00', $ajena->fresh()->current_balance);
        $this->assertDatabaseCount('receivable_payments', 0);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_no_se_puede_cobrar_a_una_cuenta_de_otro_hogar(): void
    {
        [$owner, $household] = $this->setupHousehold();
        [, $otroHogar] = $this->setupHousehold('Hogar B');
        $r = $this->receivableFor($household);
        $cuentaAjena = Account::factory()->create(['household_id' => $otroHogar->id]);

        $this->actingAs($owner)->post(route('receivables.payments.store', $r), [
            'amount' => 10000,
            'date' => now()->toDateString(),
            'type' => ReceivablePaymentType::Received->value,
            'account_id' => $cuentaAjena->id,
        ])->assertSessionHasErrors('account_id');
    }
}
