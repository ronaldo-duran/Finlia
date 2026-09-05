<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Purga diaria de cuentas cuya ventana de 30 días expiró (Plan 05, ADR-0033).
 *
 * Dos categorías:
 *  1. Cuentas con deletion_requested_at > 30 días: anonymize / cascade / transfer.
 *  2. Cuentas sin email verificado con más de 14 días: cascade completo.
 *
 * Corre vía Schedule (compatible con Hostinger shared hosting).
 * withoutOverlapping: si una purga es lenta, el cron siguiente no lanza otra encima.
 */
class PurgePendingDeletions extends Command
{
    protected $signature = 'finlia:purge-pending-deletions';

    protected $description = 'Purga cuentas en eliminación cuya ventana expiró y cuentas fantasma sin verificar (Plan 05, ADR-0033)';

    public function handle(AccountDeletionService $service): int
    {
        $this->purgeSuspended($service);
        $this->purgeUnverified($service);

        return self::SUCCESS;
    }

    private function purgeSuspended(AccountDeletionService $service): void
    {
        $cutoff = now()->subDays(AccountDeletionService::SUSPENSION_DAYS);

        $users = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $cutoff)
            ->get();

        if ($users->isEmpty()) {
            $this->info('Sin cuentas suspendidas que purgar.');

            return;
        }

        $purged = 0;

        foreach ($users as $user) {
            try {
                $service->purge($user);
                $purged++;
            } catch (Throwable $e) {
                Log::error('Error al purgar cuenta suspendida.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error al purgar user #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cuentas suspendidas purgadas: {$purged}.");
    }

    private function purgeUnverified(AccountDeletionService $service): void
    {
        // Cuentas fantasma: registradas pero nunca verificadas en 14 días.
        $cutoff = now()->subDays(14);

        $users = User::query()
            ->whereNull('email_verified_at')
            ->whereNull('deletion_requested_at')
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($users->isEmpty()) {
            $this->info('Sin cuentas fantasma que purgar.');

            return;
        }

        $purged = 0;

        foreach ($users as $user) {
            try {
                $service->purgeUnverified($user);
                $purged++;
            } catch (Throwable $e) {
                Log::error('Error al purgar cuenta fantasma.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error al purgar cuenta fantasma user #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cuentas fantasma purgadas: {$purged}.");
    }
}
