<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Aviso de errores en producción (plan de lanzamiento, T2; ADR-0044).
 *
 * Lee lo nuevo del log desde la corrida anterior y, si hay errores, manda UN
 * correo al buzón de contacto con los errores agrupados. Sin errores, silencio.
 *
 * De una excepción el correo lleva solo la clase y dónde ocurrió, nunca el
 * mensaje: el de una QueryException incluye el SQL con los valores sustituidos
 * (montos, correos), y ese detalle no debe salir del hosting. Se queda en el log
 * del servidor.
 */
class ReportLogErrors extends Command
{
    protected $signature = 'finlia:report-errors';

    protected $description = 'Avisa por correo de los errores nuevos del log de la aplicación';

    // Límite conocido: en una avalancha solo se leen los últimos 5 MB nuevos, que
    // bastan para saber qué está roto sin agotar la memoria.
    private const MAX_BYTES = 5 * 1024 * 1024;

    // Bytes del principio del archivo que lo identifican. Si cambian, el log se
    // reemplazó (rotación o borrado) aunque el nuevo ya sea más largo que la marca.
    private const HUELLA_BYTES = 256;

    public function handle(): int
    {
        // Límite conocido: solo el canal `single`, el de producción (DEPLOYMENT §4).
        // Con `daily` el archivo cambia de nombre cada día.
        $log = (string) config('logging.channels.single.path');

        // Junto al log y no en caché: un despliegue que limpie la caché perdería
        // justo los errores de la hora del despliegue.
        $marca = $log.'.offset';

        if (! is_file($log)) {
            $this->info('No hay log todavía.');

            return self::SUCCESS;
        }

        $tamano = (int) filesize($log);

        // Primera corrida: se vigila desde ahora, no se reporta el historial.
        if (! is_file($marca)) {
            $this->guardarMarca($marca, $log, $tamano);
            $this->info('Primera corrida: se vigila el log desde ahora.');

            return self::SUCCESS;
        }

        [$desde, $largo, $huella] = array_pad(explode(':', (string) file_get_contents($marca), 3), 3, '');
        $desde = (int) $desde;

        // Otro archivo (reemplazado) o uno más corto (vaciado): desde el principio.
        if ($desde > $tamano || $this->huella($log, (int) $largo) !== $huella) {
            $desde = 0;
        }

        $inicio = max($desde, $tamano - self::MAX_BYTES);
        $nuevo = (string) file_get_contents($log, false, null, $inicio);

        // Solo líneas completas: la última puede estar escribiéndose ahora mismo.
        // La marca avanza exactamente lo leído, no el tamaño medido antes de leer.
        $fin = strrpos($nuevo, "\n");
        $nuevo = $fin === false ? '' : substr($nuevo, 0, $fin + 1);
        $leido = $inicio + strlen($nuevo);

        preg_match_all(
            '/^\[([^\]]+)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m',
            $nuevo,
            $errores,
            PREG_SET_ORDER,
        );

        if ($errores !== []) {
            $veces = [];
            $ultima = [];

            foreach ($errores as [, $fecha, $nivel, $resto]) {
                $clave = $nivel.': '.$this->describir($resto);
                $veces[$clave] = ($veces[$clave] ?? 0) + 1;
                $ultima[$clave] = $fecha;
            }

            $cuerpo = collect($veces)
                ->map(fn (int $n, string $clave) => "{$n}× · última: {$ultima[$clave]}\n{$clave}")
                ->implode("\n\n")
                ."\n\nEl detalle de cada error está en storage/logs/laravel.log, en el servidor.";

            $this->line($cuerpo);

            $buzon = config('finlia.contact.inbox');

            if ($buzon && mail_is_deliverable()) {
                try {
                    Mail::raw($cuerpo, fn ($m) => $m->to($buzon)->subject('Finlia: '.count($errores).' error(es) nuevo(s) en el log'));
                } catch (Throwable $e) {
                    // WARNING y no ERROR: este comando no recoge warnings, así que el
                    // fallo no vuelve a reportarse a sí mismo. La marca no avanza.
                    Log::warning('Aviso de errores: no se pudo enviar el correo.', ['error' => $e->getMessage()]);
                    $this->error('No se pudo enviar el aviso: se reintentará en la corrida siguiente.');

                    return self::FAILURE;
                }
            } else {
                $this->warn('Sin FINLIA_CONTACT_EMAIL o sin correo real: el aviso no se envía.');
            }
        }

        $this->guardarMarca($marca, $log, $leido);

        return self::SUCCESS;
    }

    /**
     * De una excepción, la clase y el archivo:línea; de un mensaje escrito por la
     * app, el texto sin su contexto (AGENTS §2.4 prohíbe loguear datos personales).
     */
    private function describir(string $resto): string
    {
        if (preg_match('/\[object\] \(([\w\\\\]+)\(code: [^)]*\): .* at (.+?):(\d+)\)/', $resto, $m)) {
            $clase = stripslashes($m[1]); // el JSON del log escapa las barras
            $archivo = Str::after(stripslashes($m[2]), base_path().DIRECTORY_SEPARATOR);

            return "{$clase} en {$archivo}:{$m[3]}";
        }

        return Str::limit(trim((string) preg_replace('/ (\{.*\}|\[\])( \[\])?$/', '', $resto)), 200);
    }

    private function guardarMarca(string $marca, string $log, int $posicion): void
    {
        $largo = min(self::HUELLA_BYTES, (int) filesize($log));

        file_put_contents($marca, $posicion.':'.$largo.':'.$this->huella($log, $largo));
    }

    private function huella(string $log, int $largo): string
    {
        return md5((string) file_get_contents($log, false, null, 0, $largo));
    }
}
