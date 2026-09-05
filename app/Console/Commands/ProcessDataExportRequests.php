<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DataExportReadyMail;
use App\Models\User;
use App\Services\DataExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Procesa las solicitudes de exportación de datos pendientes (Plan 06, ADR-0034).
 *
 * Corre en horas valle (02:00) para no competir con el tráfico web.
 * Por cada usuario con data_export_requested_at != null:
 *   1. Genera el ZIP del hogar activo.
 *   2. Envía el correo con el ZIP adjunto.
 *   3. Limpia data_export_requested_at (null) → el usuario puede volver a pedir.
 *
 * Si falla para un usuario, registra el error y continúa con el siguiente.
 */
class ProcessDataExportRequests extends Command
{
    protected $signature = 'finlia:process-export-requests';

    protected $description = 'Genera y envía por correo las exportaciones de datos pendientes';

    public function __construct(private readonly DataExportService $exportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pending = User::whereNotNull('data_export_requested_at')
            ->whereNull('deletion_requested_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Sin solicitudes de exportación pendientes.');

            return self::SUCCESS;
        }

        $this->info("Procesando {$pending->count()} solicitud(es) de exportación...");

        foreach ($pending as $user) {
            try {
                $household = $user->households()
                    ->orderByPivot('joined_at')
                    ->first();

                if ($household === null) {
                    $this->warn("Usuario #{$user->id} sin hogar activo — omitiendo.");
                    $user->data_export_requested_at = null;
                    $user->save();

                    continue;
                }

                $zipPath = $this->exportService->buildZip($household, $user);
                $zipName = 'finlia-'.$household->id.'-'.now()->format('Ymd').'.zip';

                Mail::to($user->email)->send(new DataExportReadyMail($user, $zipPath, $zipName));

                @unlink($zipPath);

                $user->data_export_requested_at = null;
                $user->save();

                $this->info("  ✓ Exportación enviada a {$user->email}");

            } catch (Throwable $e) {
                $this->error("  ✗ Error para usuario #{$user->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
