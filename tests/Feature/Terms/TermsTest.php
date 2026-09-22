<?php

namespace Tests\Feature\Terms;

use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Términos y condiciones versionados (Plan 03, ADR-0031): re-aceptación
 * obligatoria para navegar, consentimiento versionado con IP e
 * inmutabilidad del historial.
 */
class TermsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Publica una versión de términos. Por defecto la inicial.
     */
    private function version(array $attributes = []): TermsVersion
    {
        return TermsVersion::create([
            'version' => '2026-09-v1',
            'title' => 'Términos y condiciones de uso',
            'content' => "Primer párrafo del borrador.\n\nSegundo párrafo con los puntos del acuerdo.",
            'change_summary' => null,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_sin_version_publicada_la_app_se_usa_normal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_sin_aceptar_no_se_puede_navegar(): void
    {
        $this->version();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('terms.accept'));
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertRedirect(route('terms.accept'));
    }

    public function test_la_pantalla_de_aceptacion_requiere_sesion(): void
    {
        $this->version();

        $this->get(route('terms.accept'))->assertRedirect(route('login'));
    }

    public function test_un_correo_sin_confirmar_no_llega_a_los_terminos(): void
    {
        $this->version();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('terms.accept'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_la_pantalla_muestra_el_texto_completo_y_las_dos_salidas(): void
    {
        $this->version();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('terms.accept'))
            ->assertOk()
            ->assertSee('Términos y condiciones de uso')
            ->assertSee('2026-09-v1')
            ->assertSee('Primer párrafo del borrador.')
            ->assertSee('Segundo párrafo con los puntos del acuerdo.')
            ->assertSee('Aceptar y continuar')
            ->assertSee('No aceptar');
    }

    public function test_quien_ya_acepto_la_vigente_no_vuelve_a_ver_la_pantalla(): void
    {
        $version = $this->version();
        $user = User::factory()->create();
        $user->acceptTerms($version);

        $this->actingAs($user)->get(route('terms.accept'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_aceptar_registra_version_fecha_e_ip(): void
    {
        $version = $this->version();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('terms.accept.store'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $aceptacion = $user->acceptedTerms()->first();
        $this->assertNotNull($aceptacion);
        $this->assertTrue($aceptacion->termsVersion->is($version));
        $this->assertNotNull($aceptacion->accepted_at);
        $this->assertSame('127.0.0.1', $aceptacion->ip_address);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('households.create'));
    }

    public function test_re_aceptar_es_idempotente(): void
    {
        $version = $this->version();
        $user = User::factory()->create();
        $user->acceptTerms($version, '192.0.2.10');
        $original = $user->acceptedTerms()->first();

        $this->travel(1)->hours();
        $this->actingAs($user)->post(route('terms.accept.store'));

        $this->assertSame(1, $user->acceptedTerms()->count());
        $aceptacion = $user->acceptedTerms()->first();
        $this->assertTrue($aceptacion->accepted_at->equalTo($original->accepted_at));
        $this->assertSame('192.0.2.10', $aceptacion->ip_address);
    }

    public function test_rechazar_no_toca_nada(): void
    {
        $this->version();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('terms.reject'))
            ->assertOk()
            ->assertSee('no borra nada');

        $this->assertSame(0, $user->acceptedTerms()->count());
        $this->actingAs($user)->get(route('terms.accept'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('terms.accept'));
    }

    public function test_publicar_una_version_nueva_re_exige_la_aceptacion(): void
    {
        $v1 = $this->version();
        $user = User::factory()->create();
        $user->acceptTerms($v1);

        $this->travel(2)->weeks();
        $this->version([
            'version' => '2026-09-v2',
            'published_at' => now(),
            'change_summary' => 'Se aclara el tratamiento de datos del punto 3.',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('terms.accept'));

        $this->actingAs($user)->get(route('terms.accept'))
            ->assertOk()
            ->assertSee('Ya habías aceptado la versión 2026-09-v1')
            ->assertSee('Se aclara el tratamiento de datos del punto 3.');

        $this->actingAs($user)->post(route('terms.accept.store'));
        $this->assertSame(2, $user->acceptedTerms()->count());
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('households.create'));
    }

    public function test_la_vigente_es_publica(): void
    {
        $this->version();

        $this->get(route('terms.show'))
            ->assertOk()
            ->assertSee('Términos y condiciones de uso')
            ->assertSee('2026-09-v1')
            ->assertSee('Vigente');
    }

    public function test_el_historico_es_publico_y_la_version_inexistente_da_404(): void
    {
        $this->version();
        $this->version(['version' => '2026-09-v2', 'published_at' => now()->addMinute()]);

        $this->get(route('terms.version', '2026-09-v1'))
            ->assertOk()
            ->assertSee('Versión histórica')
            ->assertSee('2026-09-v1');

        $this->get(route('terms.version', '2030-01-v9'))->assertNotFound();

        $this->get('/terminos/lo-que-sea')->assertNotFound();
    }

    public function test_sin_ninguna_version_la_lectura_publica_da_404(): void
    {
        $this->get(route('terms.show'))->assertNotFound();
    }

    public function test_una_version_con_aceptaciones_no_se_puede_borrar(): void
    {
        $version = $this->version();
        $user = User::factory()->create();
        $user->acceptTerms($version);

        $this->expectException(QueryException::class);
        $version->delete();
    }
}
