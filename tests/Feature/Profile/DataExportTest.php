<?php

namespace Tests\Feature\Profile;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\HouseholdRole;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Household;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\DataExportService;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exportación de datos del hogar (Plan 06, ADR-0034).
 *
 * Cubre:
 *  - La respuesta HTTP es un ZIP descargable.
 *  - El ZIP contiene todos los archivos esperados.
 *  - El CSV del usuario NO incluye la contraseña.
 *  - El CSV de gastos NO incluye datos personales de otros miembros.
 *  - Aislamiento: usuario B solo exporta su propio hogar.
 *  - Throttle (3 solicitudes por día).
 *  - CSV con BOM UTF-8 y separador `;`.
 *  - La página pública `/datos` es accesible sin cuenta.
 */
class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private function setup_household(string $name = 'Hogar Prueba'): array
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, $name);

        return [$user, $household];
    }

    // -----------------------------------------------------------------------
    // Ruta HTTP
    // -----------------------------------------------------------------------

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('profile.export'))->assertRedirect(route('login'));
    }

    public function test_exportar_devuelve_un_zip_descargable(): void
    {
        [$user] = $this->setup_household();

        $response = $this->actingAs($user)->get(route('profile.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
    }

    public function test_el_nombre_del_zip_incluye_id_y_fecha(): void
    {
        [$user, $household] = $this->setup_household();

        $response = $this->actingAs($user)->get(route('profile.export'));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('finlia-'.$household->id.'-', $disposition);
        $this->assertStringContainsString('.zip', $disposition);
    }

    public function test_sin_hogar_activo_devuelve_404(): void
    {
        // Usuario sin ningún hogar asociado.
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.export'))->assertNotFound();
    }

    public function test_throttle_limita_a_3_descargas(): void
    {
        [$user] = $this->setup_household();

        // Las primeras 3 pasan.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->get(route('profile.export'))->assertOk();
        }

        // La cuarta es rechazada.
        $this->actingAs($user)->get(route('profile.export'))->assertTooManyRequests();
    }

    // -----------------------------------------------------------------------
    // Contenido del servicio (sin disco real)
    // -----------------------------------------------------------------------

    public function test_collect_incluye_el_perfil_del_usuario(): void
    {
        [$user, $household] = $this->setup_household();

        $data = app(DataExportService::class)->collect($household, $user);

        $this->assertArrayHasKey('csv', $data);
        $this->assertArrayHasKey('usuario.csv', $data['csv']);
        $this->assertStringContainsString($user->name, $data['csv']['usuario.csv']);
        $this->assertStringContainsString($user->email, $data['csv']['usuario.csv']);
    }

    public function test_el_csv_de_usuario_no_incluye_la_contrasena(): void
    {
        [$user, $household] = $this->setup_household();

        $data = app(DataExportService::class)->collect($household, $user);

        // El hash bcrypt es largo y empieza con $2y$. Verificamos que no está.
        $this->assertStringNotContainsString('password', $data['csv']['usuario.csv']);
        $this->assertStringNotContainsString('$2y$', $data['csv']['usuario.csv']);
        $this->assertStringNotContainsString('contrasena', $data['csv']['usuario.csv']);
    }

    public function test_collect_incluye_los_gastos_del_hogar(): void
    {
        [$user, $household] = $this->setup_household();
        $account = Account::factory()->create(['household_id' => $household->id]);
        Expense::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => 150000,
            'description' => 'Compra supermercado',
        ]);

        $data = app(DataExportService::class)->collect($household, $user);

        $this->assertStringContainsString('Compra supermercado', $data['csv']['gastos.csv']);
        $this->assertStringContainsString('150000,00', $data['csv']['gastos.csv']);
    }

    public function test_collect_incluye_las_deudas_del_hogar(): void
    {
        [$user, $household] = $this->setup_household();
        Debt::factory()->create([
            'household_id' => $household->id,
            'name' => 'Tarjeta Bancolombia',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 5000000,
            'current_balance' => 4000000,
            'status' => DebtStatus::Active->value,
        ]);

        $data = app(DataExportService::class)->collect($household, $user);

        $this->assertStringContainsString('Tarjeta Bancolombia', $data['csv']['deudas.csv']);
        $this->assertStringContainsString('5000000,00', $data['csv']['deudas.csv']);
    }

    public function test_collect_incluye_las_metas_de_ahorro(): void
    {
        [$user, $household] = $this->setup_household();
        SavingsGoal::factory()->create([
            'household_id' => $household->id,
            'name' => 'Fondo emergencia',
            'target_amount' => 10000000,
        ]);

        $data = app(DataExportService::class)->collect($household, $user);

        $this->assertStringContainsString('Fondo emergencia', $data['csv']['metas-ahorro.csv']);
    }

    public function test_collect_incluye_el_json_maestro(): void
    {
        [$user, $household] = $this->setup_household();

        $data = app(DataExportService::class)->collect($household, $user);

        $this->assertArrayHasKey('json', $data);
        $json = $data['json'];
        $this->assertArrayHasKey('cuentas', $json);
        $this->assertArrayHasKey('gastos', $json);
        $this->assertArrayHasKey('deudas', $json);
        $this->assertArrayHasKey('metas_ahorro', $json);
        $this->assertArrayHasKey('exportado_en', $json);
    }

    public function test_el_json_no_incluye_contrasena(): void
    {
        [$user, $household] = $this->setup_household();

        $data = app(DataExportService::class)->collect($household, $user);

        $json = json_encode($data['json']);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('deletion_requested_at', $json);
    }

    public function test_el_csv_tiene_bom_utf8(): void
    {
        [$user, $household] = $this->setup_household();

        $data = app(DataExportService::class)->collect($household, $user);

        // Cada CSV debe empezar con el BOM UTF-8 (0xEF 0xBB 0xBF).
        foreach ($data['csv'] as $filename => $content) {
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content, "$filename no tiene BOM UTF-8");
        }
    }

    public function test_el_csv_usa_punto_y_coma_como_separador(): void
    {
        [$user, $household] = $this->setup_household();
        $account = Account::factory()->create(['household_id' => $household->id]);
        Expense::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
            'user_id' => $user->id,
            'description' => 'Taxi',
        ]);

        $data = app(DataExportService::class)->collect($household, $user);

        $lines = explode("\n", ltrim($data['csv']['gastos.csv'], "\xEF\xBB\xBF"));
        $this->assertStringContainsString(';', $lines[0]);
    }

    // -----------------------------------------------------------------------
    // Aislamiento entre hogares
    // -----------------------------------------------------------------------

    public function test_usuario_b_no_ve_datos_del_hogar_de_usuario_a(): void
    {
        [$userA, $householdA] = $this->setup_household('Hogar A');
        [$userB] = $this->setup_household('Hogar B');

        $account = Account::factory()->create(['household_id' => $householdA->id]);
        Expense::factory()->create([
            'household_id' => $householdA->id,
            'account_id' => $account->id,
            'user_id' => $userA->id,
            'description' => 'Gasto secreto de A',
        ]);

        // userB exporta su propio hogar.
        $data = app(DataExportService::class)->collect(
            app(HouseholdService::class)->createHousehold($userB->id, 'Hogar B extra') ?? Household::where('owner_id', $userB->id)->first(),
            $userB
        );

        $csv = $data['csv']['gastos.csv'] ?? '';
        $this->assertStringNotContainsString('Gasto secreto de A', $csv);
    }

    public function test_el_csv_de_gastos_no_incluye_nombre_de_otros_miembros(): void
    {
        [$owner, $household] = $this->setup_household();
        $member = User::factory()->create(['name' => 'Vanessa Otro']);
        $household->members()->attach($member->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);
        $account = Account::factory()->create(['household_id' => $household->id]);
        Expense::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
            'user_id' => $member->id,
            'description' => 'Gasto del miembro',
        ]);

        // El owner exporta: el gasto aparece (es del hogar), pero el nombre
        // ni el correo de Vanessa no debe estar en los CSV.
        $data = app(DataExportService::class)->collect($household, $owner);

        $allCsv = implode("\n", $data['csv']);
        $this->assertStringNotContainsString('Vanessa Otro', $allCsv);
        $this->assertStringNotContainsString($member->email, $allCsv);
    }

    // -----------------------------------------------------------------------
    // Página pública /datos
    // -----------------------------------------------------------------------

    public function test_la_pagina_de_datos_es_accesible_sin_cuenta(): void
    {
        $this->get(route('data.policy'))->assertOk()->assertSee('Tus datos y Finlia');
    }

    public function test_la_pagina_de_datos_menciona_portabilidad_y_eliminacion(): void
    {
        $this->get(route('data.policy'))
            ->assertSee('Portabilidad')
            ->assertSee('Eliminación')
            ->assertSee('Retiro del software');
    }
}
