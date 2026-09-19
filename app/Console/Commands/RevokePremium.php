<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Household;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Devuelve un hogar al plan Free (Épica 12, v0.38).
 * Ejemplo: php artisan finlia:revoke-premium --household=17 --reason="Fin de prueba"
 */
class RevokePremium extends Command
{
    protected $signature = 'finlia:revoke-premium
        {--household= : ID del hogar}
        {--reason= : Motivo humano legible}';

    protected $description = 'Devuelve un hogar al plan Free (Épica 12).';

    public function handle(SubscriptionService $subscriptions): int
    {
        $id = $this->option('household');
        if ($id === null) {
            $this->error('Falta --household=ID.');

            return self::INVALID;
        }

        $household = Household::find((int) $id);
        if ($household === null) {
            $this->error("Hogar #{$id} no encontrado.");

            return self::FAILURE;
        }

        $reason = (string) ($this->option('reason') ?: 'Revocación manual');
        $subscriptions->revoke($household, $reason);

        $this->info("✓ Hogar #{$household->id} ({$household->name}) → Free (razón: {$reason})");

        return self::SUCCESS;
    }
}
