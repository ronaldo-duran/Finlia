<?php

namespace Tests\Feature;

use App\Models\TermsVersion;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas básicas de la PWA (Épica 10).
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_webmanifest_es_accesible_con_content_type_correcto(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json');
    }

    public function test_manifest_contiene_campos_obligatorios(): void
    {
        $response = $this->get(route('pwa.manifest'));
        $content = $response->streamedContent();

        $manifest = json_decode($content, true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('display', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        $this->assertSame('standalone', $manifest['display']);
    }

    public function test_layout_autenticado_incluye_manifest_link(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Asegurar que el usuario tenga hogar activo.
        app(HouseholdService::class)->createHousehold($user->id, 'Hogar Test');

        // Aceptar términos si existen.
        if (TermsVersion::exists()) {
            $terms = TermsVersion::latest()->first();
            $user->termsAcceptances()->create([
                'terms_version_id' => $terms->id,
                'accepted_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false);
    }

    public function test_sw_js_existe_en_public(): void
    {
        // El service worker es servido directamente por el servidor web (no
        // por PHP), así que su accesibilidad no se puede probar desde el
        // test client. Solo verificamos que el archivo existe en public/.
        $this->assertFileExists(public_path('sw.js'));
    }
}
