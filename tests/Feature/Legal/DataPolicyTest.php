<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Política de tratamiento de datos (/datos).
 *
 * Es una página pública y legal: quien la lee está decidiendo si confía sus
 * finanzas a Finlia. Lo que se fija aquí no es la redacción —esa cambiará—
 * sino que no le falte ninguno de los elementos que la Ley 1581 exige, y que
 * no se publique con marcadores a medio escribir, que es exactamente lo que
 * llegó a estar a punto de pasar.
 */
class DataPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_publica_y_no_exige_sesion(): void
    {
        $this->get(route('data.policy'))->assertOk();
    }

    /**
     * Un `<!-- COMPLETAR -->` no rompe nada y no se ve en el HTML renderizado,
     * pero el texto a medias que lo acompaña sí: la página llegó a mostrar
     * literalmente "[60 / 90] días" y una frase que terminaba en "escríbenos a .".
     */
    public function test_no_queda_ningun_marcador_sin_rellenar(): void
    {
        $html = $this->get(route('data.policy'))->assertOk()->getContent();

        foreach (['COMPLETAR', 'BORRADOR', 'borrador', '[60 / 90]', 'TODO', 'PENDIENTE'] as $marcador) {
            $this->assertStringNotContainsString(
                $marcador,
                $html,
                "La política de datos se publica con el marcador «{$marcador}» sin rellenar.",
            );
        }
    }

    /**
     * Los elementos que la Ley 1581 de 2012 exige informar al titular.
     */
    public function test_informa_lo_que_la_ley_exige(): void
    {
        // Texto plano y espacios normalizados: en el HTML la frase se parte en
        // dos líneas con un <strong> en medio, y comprobar la cadena literal
        // haría que el test dependiera del ancho del renglón, no del contenido.
        $texto = preg_replace('/\s+/u', ' ', strip_tags(
            $this->get(route('data.policy'))->assertOk()->getContent()
        ));

        $obligatorios = [
            'Ley 1581' => 'la norma que rige el tratamiento',
            'responsable del tratamiento' => 'quién responde por los datos',
            'contacto@finlia.online' => 'el canal para ejercer derechos',
            'Superintendencia de Industria y Comercio' => 'la autoridad ante la que reclamar',
            'diez (10) días hábiles' => 'el plazo legal de las consultas',
            'quince (15) días hábiles' => 'el plazo legal de los reclamos',
            'transferencia internacional' => 'que los servidores están fuera del país',
        ];

        foreach ($obligatorios as $fragmento => $porque) {
            $this->assertStringContainsString(
                $fragmento,
                $texto,
                "La política de datos no informa {$porque} («{$fragmento}»).",
            );
        }
    }

    /**
     * El plazo de retiro debe coincidir con el de los términos: si se
     * contradicen, vale el que peor deja al usuario y la promesa se rompe.
     */
    public function test_el_plazo_de_aviso_de_cierre_coincide_con_los_terminos(): void
    {
        $this->get(route('data.policy'))
            ->assertOk()
            ->assertSee('90 días', false);
    }
}
