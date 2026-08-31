<?php

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

class TermsVersionSeeder extends Seeder
{
    /**
     * Publica la versión inicial de los términos y condiciones (Plan 03).
     *
     * ⚠️ El contenido es un BORRADOR con marcadores: la redacción legal
     * definitiva es del dueño del producto, idealmente con revisión de
     * abogado (Ley 1581 de 2012), y NINGÚN agente debe redactarla por su
     * cuenta. Cuando exista el texto definitivo se publica como OTRA
     * versión (nueva fila): esta jamás se edita (inmutabilidad, ADR-0031).
     */
    public function run(): void
    {
        if (TermsVersion::query()->exists()) {
            return;
        }

        TermsVersion::create([
            'version' => '2026-09-v1',
            'title' => 'Términos y condiciones de uso',
            'change_summary' => null,
            'published_at' => now(),
            'content' => implode("\n\n", [
                '⚠️ BORRADOR — Este texto es un marcador de demostración. Debe reemplazarse por la redacción legal definitiva antes de abrir el registro real. Aplican la Ley 1581 de 2012 y demás normas colombianas vigentes.',

                '1. Objeto. Finlia es una aplicación de gestión de finanzas personales y familiares. Estas condiciones regulan el uso del servicio.',

                '2. Cuenta. Para usar Finlia necesitas una cuenta con un correo verificado. Eres responsable de la custodia de tu contraseña y de la actividad realizada desde tu cuenta.',

                '3. Tus datos. [BORRADOR: resumen del tratamiento — qué datos financieros registras, con qué finalidad, correo transaccional y canales para ejercer tus derechos (Habeas Data, Ley 1581 de 2012). Redactar junto con la política de datos.]',

                '4. Hogares compartidos. Puedes invitar a otras personas a tu hogar; la información financiera del hogar es visible para todos sus miembros.',

                '5. Cambios en los términos. Si actualizamos estos términos publicaremos una versión nueva y deberás aceptarla para seguir usando la app. El historial de versiones queda disponible públicamente.',

                '6. Sin asesoría financiera. Finlia es una herramienta de organización; nada aquí constituye asesoría financiera, legal o tributaria.',
            ]),
        ]);
    }
}
