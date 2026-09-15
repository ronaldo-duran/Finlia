<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Aviso de errores en producción (plan de lanzamiento, T2; ADR-0044).
 *
 * Lee lo nuevo del log desde la corrida anterior y, si hay errores, manda UN
 * correo al buzón de contacto con los mensajes agrupados. Sin errores, silencio.
 * Sin dependencias ni servicios externos: del log solo sale la primera línea de
 * cada error, en ese correo.
 *
 * También deja ver cuándo Brevo agota la cuota diaria: el envío rechazado lanza
 * una excepción y queda en el log.
 */
class ReportLogErrors extends Command
{
    protected $signature = 'finlia:report-errors';

    protected $description = 'Avisa por correo de los errores nuevos del log de la aplicación';

    // ponytail: tope de lectura por corrida. En una avalancha solo se leen los
    // últimos 5 MB, que bastan para saber qué está roto sin agotar la memoria.
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function handle(): int
    {
        // ponytail: solo el canal `single`, el de producción (DEPLOYMENT §4). Con
        // `daily` el archivo cambia de nombre cada día y habría que leer el del día.
        $log = (string) config('logging.channels.single.path');

        // La marca va junto al log y no en caché: un despliegue que limpie la
        // caché perdería justo los errores de la hora del despliegue.
        $marca = $log.'.offset';

        if (! is_file($log)) {
            $this->info('No hay log todavía.');

            return self::SUCCESS;
        }

        $tamano = (int) filesize($log);

        // Primera corrida: se vigila desde ahora, no se reporta el historial.
        if (! is_file($marca)) {
            file_put_contents($marca, (string) $tamano);
            $this->info('Primera corrida: se vigila el log desde ahora.');

            return self::SUCCESS;
        }

        $desde = (int) file_get_contents($marca);

        if ($desde > $tamano) {
            $desde = 0; // el log se rotó o se vació
        }

        $nuevo = (string) file_get_contents($log, false, null, max($desde, $tamano - self::MAX_BYTES));

        preg_match_all(
            '/^\[([^\]]+)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m',
            $nuevo,
            $errores,
            PREG_SET_ORDER,
        );

        if ($errores !== []) {
            $grupos = [];

            foreach ($errores as [, $fecha, $nivel, $mensaje]) {
                $clave = Str::limit($nivel.': '.trim($mensaje), 300);
                $grupos[$clave] ??= 0;
                $grupos[$clave]++;
                $ultima[$clave] = $fecha;
            }

            $cuerpo = collect($grupos)
                ->map(fn (int $veces, string $mensaje) => "{$veces}× · última: {$ultima[$mensaje]}\n{$mensaje}")
                ->implode("\n\n");

            $this->line($cuerpo);

            $buzon = config('finlia.contact.inbox');

            if ($buzon && mail_is_deliverable()) {
                Mail::raw($cuerpo, fn ($m) => $m->to($buzon)->subject('Finlia: '.count($errores).' error(es) nuevo(s) en el log'));
            } else {
                $this->warn('Sin FINLIA_CONTACT_EMAIL o sin correo real: el aviso no se envía.');
            }
        }

        // Después de enviar: si el correo falla, la próxima corrida lo reintenta.
        file_put_contents($marca, (string) $tamano);

        return self::SUCCESS;
    }
}
