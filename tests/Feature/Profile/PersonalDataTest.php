<?php

namespace Tests\Feature\Profile;

use App\Enums\ColombianRegion;
use App\Enums\Gender;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Datos personales del perfil (Plan 04, ADR-0032): fecha de nacimiento
 * (obligatoria, 18+), región y género (opcionales, listas cerradas).
 * Nombre y contraseña viven en ProfileTest (Plan 02).
 */
class PersonalDataTest extends TestCase
{
    use RefreshDatabase;

    // ---- Pantalla ----

    public function test_el_perfil_muestra_la_seccion_de_datos_personales(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Datos personales')
            ->assertSee('Fecha de nacimiento')
            ->assertSee('Región')
            ->assertSee('Género')
            ->assertSee('Prefiero no decirlo');
    }

    public function test_el_perfil_muestra_la_region_guardada(): void
    {
        $user = User::factory()->create(['region' => ColombianRegion::ValleDelCauca->value]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Valle del Cauca');
    }

    // ---- Actualización ----

    public function test_actualiza_los_datos_personales(): void
    {
        $user = User::factory()->create(['birth_date' => '1980-01-01']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => '1990-05-12',
                'region' => ColombianRegion::Atlantico->value,
                'gender' => Gender::Female->value,
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('1990-05-12', $user->birth_date->toDateString());
        $this->assertSame(ColombianRegion::Atlantico->value, $user->region);
        $this->assertSame(Gender::Female->value, $user->gender);
    }

    public function test_region_y_genero_son_opcionales_y_se_pueden_vaciar(): void
    {
        // Minimización (Ley 1581): "prefiero no decirlo" = no almacenar nada.
        $user = User::factory()->create([
            'region' => ColombianRegion::Cundinamarca->value,
            'gender' => Gender::Male->value,
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => '1990-05-12',
                'region' => '',
                'gender' => '',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNull($user->region);
        $this->assertNull($user->gender);
    }

    public function test_usuario_heredado_completa_su_fecha_de_nacimiento(): void
    {
        // La columna nació en NULL (usuarios anteriores al plan 04); el
        // perfil exigido la completa.
        $user = User::factory()->create(['birth_date' => null]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => '1985-11-03',
                'region' => null,
                'gender' => null,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('1985-11-03', $user->refresh()->birth_date->toDateString());
    }

    // ---- Validación ----

    public function test_la_fecha_de_nacimiento_es_obligatoria_en_el_perfil(): void
    {
        $user = User::factory()->create(['birth_date' => '1990-05-12']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => $user->name, 'birth_date' => ''])
            ->assertSessionHasErrors('birth_date');

        // Nada cambió.
        $this->assertSame('1990-05-12', $user->refresh()->birth_date->toDateString());
    }

    public function test_el_perfil_rechaza_un_menor_de_edad(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => today()->subYears(16)->toDateString(),
            ])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_el_perfil_rechaza_una_fecha_futura(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => today()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_la_region_debe_pertenecer_a_la_lista(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => '1990-05-12',
                'region' => 'bogota-norte', // fuera de la lista cerrada
            ])
            ->assertSessionHasErrors('region');

        $this->assertNull($user->refresh()->region);
    }

    public function test_el_genero_debe_pertenecer_a_la_lista(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'birth_date' => '1990-05-12',
                'gender' => 'otra-cosa',
            ])
            ->assertSessionHasErrors('gender');

        $this->assertNull($user->refresh()->gender);
    }

    // ---- Aislamiento ----

    public function test_actualizar_el_perfil_no_toca_a_otro_usuario(): void
    {
        // /perfil no conoce IDs ajenos (Plan 02): solo opera sobre el
        // autenticado. Este test clava el comportamiento para los datos
        // demográficos también.
        $ana = User::factory()->create(['region' => ColombianRegion::Huila->value]);
        $beto = User::factory()->create(['region' => ColombianRegion::Meta->value]);

        $this->actingAs($ana)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Ana Nueva',
                'birth_date' => '1992-02-02',
                'region' => ColombianRegion::Cauca->value,
                'gender' => Gender::NonBinary->value,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(ColombianRegion::Cauca->value, $ana->refresh()->region);
        $this->assertSame(ColombianRegion::Meta->value, $beto->refresh()->region);
        $this->assertSame('Ana Nueva', $ana->refresh()->name);
        $this->assertNotSame('Ana Nueva', $beto->refresh()->name);
    }

    // ---- User::age() (derivado, nunca en columna) ----

    public function test_la_edad_se_calcula_desde_la_fecha_de_nacimiento(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');

        try {
            $cumpleHoy = User::factory()->make(['birth_date' => '2008-08-30']);
            $this->assertSame(18, $cumpleHoy->age());

            $cumpleManana = User::factory()->make(['birth_date' => '1990-08-31']);
            $this->assertSame(35, $cumpleManana->age());

            $heredado = User::factory()->make(['birth_date' => null]);
            $this->assertNull($heredado->age());
        } finally {
            Carbon::setTestNow();
        }
    }

    // ---- Listas cerradas ----

    public function test_la_lista_de_regiones_es_la_oficial_de_colombia(): void
    {
        // 32 departamentos + Bogotá D.C. (subdivisión propia en
        // ISO 3166-2:CO): 33 entradas, ordenadas para el select.
        $options = ColombianRegion::options();

        $this->assertCount(33, $options);
        $this->assertSame('Bogotá D.C.', $options['bogota-dc']);
        $this->assertSame('Norte de Santander', $options['norte-de-santander']);
        $this->assertSame('Antioquia', $options[ColombianRegion::Antioquia->value]);

        // Orden alfabético por etiqueta.
        $this->assertSame(array_values($options), array_values(collect($options)->sort()->all()));
    }
}
