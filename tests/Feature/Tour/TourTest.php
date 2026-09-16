<?php

namespace Tests\Feature\Tour;

use App\Enums\TourStatus;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\TourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guías de pantalla (ADR-0045).
 *
 * Lo que se fija aquí es la parte de servidor: a quién le llega la guía, con
 * qué versión vista, y que la regla de «poco invasiva» (una por sesión, solo
 * la primera vez) se cumpla de verdad. Cómo se pinta el globo es de
 * resources/js/tour.js y no se prueba desde PHPUnit.
 */
class TourTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $user = User::factory()->create();
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar de prueba');

        return $user;
    }

    /** El <script> con la guía solo se inyecta cuando hay guía que mostrar. */
    private function traeGuia(string $html): bool
    {
        return str_contains($html, 'id="finlia-tour-data"');
    }

    // ------------------------------------------------------------ arranque

    public function test_la_guia_del_panel_llega_en_la_primera_visita(): void
    {
        $respuesta = $this->actingAs($this->usuario())->get(route('dashboard'));

        $respuesta->assertOk();
        $this->assertTrue($this->traeGuia($respuesta->getContent()));
        $respuesta->assertSee('"start":"auto"', false);
        $respuesta->assertSee('"key":"panel"', false);
    }

    public function test_solo_arranca_una_guia_por_sesion(): void
    {
        $user = $this->usuario();

        // La primera quema el cupo de la sesión...
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('"start":"auto"', false);

        // ...y la siguiente pantalla ya no salta sola, aunque nunca se haya
        // visto. Sigue disponible desde el menú del avatar: por eso el bloque
        // se inyecta igualmente, pero sin arrancar.
        $segunda = $this->actingAs($user)->get(route('movements.index'));
        $segunda->assertOk();
        $this->assertTrue($this->traeGuia($segunda->getContent()));
        $segunda->assertSee('"start":null', false);
    }

    public function test_una_guia_ya_vista_no_vuelve_a_arrancar_sola(): void
    {
        $user = $this->usuario();
        app(TourService::class)->markSeen($user, 'panel', TourStatus::Completed);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('"start":null', false);
    }

    public function test_una_pantalla_sin_guia_no_inyecta_nada(): void
    {
        // /perfil no tiene guía a propósito: es donde vive el catálogo, y una
        // guía que explique la pantalla de las guías sobra.
        $respuesta = $this->actingAs($this->usuario())->get(route('profile.edit'));

        $respuesta->assertOk();
        $this->assertFalse($this->traeGuia($respuesta->getContent()));
    }

    public function test_un_post_no_gasta_el_cupo_de_la_sesion(): void
    {
        $user = $this->usuario();

        // Una escritura cualquiera antes de pisar el Panel: si el cupo se
        // gastara aquí, la persona se quedaría sin la guía de bienvenida.
        $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Mercado',
            'type' => 'expense',
            'color' => '#112233',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('"start":"auto"', false);
    }

    // ----------------------------------------------------------- novedades

    public function test_quien_ya_vio_la_guia_recibe_la_version_que_vio(): void
    {
        $user = $this->usuario();
        app(TourService::class)->markSeen($user, 'panel', TourStatus::Completed);

        // El navegador filtra los pasos con `seen`: los nacidos después de esa
        // versión son las novedades. Si el servidor mintiera aquí, o repetiría
        // la guía entera o se comería lo nuevo.
        $version = (int) config('tours.panel.version');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('"seen":'.$version, false);
    }

    public function test_un_usuario_nuevo_recibe_la_guia_completa(): void
    {
        $this->actingAs($this->usuario())->get(route('dashboard'))
            ->assertSee('"seen":0', false);
    }

    // ------------------------------------------------- volver a verla a mano

    public function test_el_parametro_guia_abre_la_guia_aunque_ya_este_vista(): void
    {
        $user = $this->usuario();
        app(TourService::class)->markSeen($user, 'panel', TourStatus::Skipped);

        $this->actingAs($user)->get(route('dashboard', ['guia' => 'panel']))
            ->assertSee('"start":"open"', false);
    }

    public function test_el_parametro_guia_funciona_con_las_guias_apagadas(): void
    {
        $user = $this->usuario();
        $user->tours_enabled = false;
        $user->save();

        // Pedir una guía a mano es lo contrario de que te la impongan: el
        // interruptor apaga lo automático, no el catálogo del perfil.
        $this->actingAs($user)->get(route('debts.index', ['guia' => 'deudas']))
            ->assertSee('"start":"open"', false);
    }

    public function test_una_clave_inventada_en_la_url_no_rompe_la_pagina(): void
    {
        $respuesta = $this->actingAs($this->usuario())
            ->get(route('dashboard', ['guia' => 'inventada']));

        // Cae a la guía de la propia pantalla, como si no hubieran pedido nada.
        $respuesta->assertOk();
        $respuesta->assertSee('"key":"panel"', false);
    }

    // ---------------------------------------------------------- interruptor

    public function test_con_las_guias_apagadas_ninguna_arranca_sola(): void
    {
        $user = $this->usuario();
        $user->tours_enabled = false;
        $user->save();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('"start":null', false);
    }

    public function test_se_pueden_apagar_las_guias(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)
            ->put(route('tours.preference'), ['enabled' => '0'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->tours_enabled);
    }

    public function test_apagar_las_guias_no_borra_el_progreso(): void
    {
        $user = $this->usuario();
        app(TourService::class)->markSeen($user, 'panel', TourStatus::Completed);

        $this->actingAs($user)->put(route('tours.preference'), ['enabled' => '0']);
        $this->actingAs($user)->put(route('tours.preference'), ['enabled' => '1']);

        // Volver a encenderlas retoma donde iba: quien las apagó y se arrepiente
        // no se merece las diez guías otra vez de golpe.
        $this->assertDatabaseCount('user_tours', 1);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('"start":null', false);
    }

    // ------------------------------------------------------------- progreso

    public function test_marcar_una_guia_como_vista_guarda_la_version_del_registro(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)
            ->postJson(route('tours.store', 'panel'), ['status' => 'completed'])
            ->assertNoContent();

        $this->assertDatabaseHas('user_tours', [
            'user_id' => $user->id,
            'key' => 'panel',
            'version' => (int) config('tours.panel.version'),
            'status' => TourStatus::Completed->value,
        ]);
    }

    public function test_la_version_no_se_acepta_desde_la_peticion(): void
    {
        $user = $this->usuario();

        // Si colara, bastaría con declararse en la versión 999 para no volver
        // a recibir una sola novedad.
        $this->actingAs($user)->postJson(route('tours.store', 'panel'), [
            'status' => 'completed',
            'version' => 999,
        ])->assertNoContent();

        $this->assertDatabaseHas('user_tours', [
            'key' => 'panel',
            'version' => (int) config('tours.panel.version'),
        ]);
    }

    public function test_una_clave_inventada_no_crea_filas(): void
    {
        $this->actingAs($this->usuario())
            ->postJson(route('tours.store', 'inventada'), ['status' => 'completed'])
            ->assertNotFound();

        $this->assertDatabaseCount('user_tours', 0);
    }

    public function test_un_estado_inventado_se_rechaza(): void
    {
        // Sin 422: esta app solo responde JSON bajo /api (bootstrap/app.php),
        // así que una validación fallida vuelve por donde vino con los errores.
        // Da igual para el navegador —el motor no mira la respuesta— y lo que
        // importa se cumple: no entra basura en la tabla.
        $this->actingAs($this->usuario())
            ->post(route('tours.store', 'panel'), ['status' => 'aprendida'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('user_tours', 0);
    }

    public function test_marcarla_dos_veces_no_duplica_filas(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->postJson(route('tours.store', 'panel'), ['status' => 'skipped']);
        $this->actingAs($user)->postJson(route('tours.store', 'panel'), ['status' => 'completed']);

        $this->assertDatabaseCount('user_tours', 1);
        $this->assertDatabaseHas('user_tours', ['status' => TourStatus::Completed->value]);
    }

    public function test_el_progreso_es_de_cada_usuario(): void
    {
        $uno = $this->usuario();
        $otro = $this->usuario();

        $this->actingAs($uno)->postJson(route('tours.store', 'panel'), ['status' => 'completed']);

        // Ninguna ruta lleva id de usuario: no hay forma de marcarle la guía a
        // otro, y la del otro sigue esperándolo.
        $this->assertDatabaseMissing('user_tours', ['user_id' => $otro->id]);
        $this->actingAs($otro)->get(route('dashboard'))->assertSee('"start":"auto"', false);
    }

    public function test_reiniciar_borra_el_progreso_y_enciende_las_guias(): void
    {
        $user = $this->usuario();
        app(TourService::class)->markSeen($user, 'panel', TourStatus::Completed);
        $user->tours_enabled = false;
        $user->save();

        $this->actingAs($user)->delete(route('tours.destroy'))->assertRedirect();

        $this->assertDatabaseCount('user_tours', 0);
        $this->assertTrue($user->fresh()->tours_enabled);
    }

    public function test_reiniciar_solo_borra_lo_propio(): void
    {
        $uno = $this->usuario();
        $otro = $this->usuario();
        app(TourService::class)->markSeen($uno, 'panel', TourStatus::Completed);
        app(TourService::class)->markSeen($otro, 'panel', TourStatus::Completed);

        $this->actingAs($uno)->delete(route('tours.destroy'));

        $this->assertDatabaseCount('user_tours', 1);
        $this->assertDatabaseHas('user_tours', ['user_id' => $otro->id]);
    }

    // ---------------------------------------------------------------- acceso

    public function test_un_invitado_no_puede_tocar_las_guias(): void
    {
        $this->post(route('tours.store', 'panel'), ['status' => 'completed'])
            ->assertRedirect(route('login'));
        $this->put(route('tours.preference'), ['enabled' => '0'])
            ->assertRedirect(route('login'));
        $this->delete(route('tours.destroy'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_tours', 0);
    }

    // ------------------------------------------------------------- catálogo

    public function test_el_perfil_muestra_el_catalogo_de_guias(): void
    {
        $respuesta = $this->actingAs($this->usuario())->get(route('profile.edit'));

        $respuesta->assertOk();
        $respuesta->assertSee('Guías de la app');
        $respuesta->assertSee('Volver a verlas desde el principio');

        // Todas las guías del registro. Las que se pueden enlazar traen su
        // «Ver»; las de pantallas con id en la URL (el detalle de una deuda)
        // traen la pista de dónde encontrarlas, que es lo único honesto.
        foreach (config('tours') as $key => $guide) {
            $respuesta->assertSee($guide['title']);

            if ($guide['link'] !== null) {
                $respuesta->assertSee(route($guide['link'], ['guia' => $key]), false);
            } else {
                $respuesta->assertSee($guide['link_hint']);
            }
        }
    }

    public function test_el_menu_ofrece_la_guia_solo_donde_la_hay(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Guía de esta pantalla');

        // /perfil no tiene guía: ofrecer un botón que no abre nada sería peor
        // que no ofrecer ninguno.
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertDontSee('Guía de esta pantalla');
    }
}
