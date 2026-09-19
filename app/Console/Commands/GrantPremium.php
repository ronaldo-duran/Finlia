<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Otorga Premium a un hogar sin pasarela (Épica 12, v0.38).
 *
 * Antes de v0.41 (Wompi), esta es la vía real de activación: se corre desde
 * cron / SSH cuando alguien paga a mano (transferencia, Nequi, contacto
 * comercial). Toda activación queda registrada con su fecha de expiración
 * y su motivo para auditoría — no hay "premium para siempre por accidente".
 *
 * Formas de uso:
 *   php artisan finlia:grant-premium --household=17 --until=2026-12-31
 *   php artisan finlia:grant-premium --email=ana@correo.com --until=2027-01-15 --reason="Beta"
 */
class GrantPremium extends Command
{
    protected $signature = 'finlia:grant-premium
        {--household= : ID del hogar destino}
        {--email= : Correo del owner; se aplica a los hogares que administra}
        {--until= : Fecha de expiración YYYY-MM-DD (por defecto: 1 mes desde hoy)}
        {--reason= : Motivo humano legible que queda en la fila}';

    protected $description = 'Otorga Premium a uno o varios hogares hasta la fecha indicada (Épica 12).';

    public function handle(SubscriptionService $subscriptions): int
    {
        $householdOption = $this->option('household');
        $emailOption = $this->option('email');

        if ($householdOption === null && $emailOption === null) {
            $this->error('Debes indicar --household=ID o --email=usuario@correo.');

            return self::INVALID;
        }

        $until = $this->option('until') !== null
            ? Carbon::parse((string) $this->option('until'))->endOfDay()
            : now()->addMonthNoOverflow()->endOfDay();

        $reason = (string) ($this->option('reason') ?: 'Concesión manual');

        $households = $this->resolveHouseholds($householdOption, $emailOption);

        if ($households->isEmpty()) {
            $this->warn('Ningún hogar coincide con los filtros dados.');

            return self::SUCCESS;
        }

        foreach ($households as $household) {
            $subscription = $subscriptions->grantPremium($household, $until, $reason);

            $this->info(sprintf(
                '✓ Hogar #%d (%s) → Premium hasta %s (razón: %s)',
                $household->id,
                $household->name,
                $subscription->ends_at?->toDateString() ?? 'sin fecha',
                $reason,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Household>
     */
    private function resolveHouseholds(?string $householdId, ?string $email): Collection
    {
        if ($householdId !== null) {
            $household = Household::find((int) $householdId);

            return $household !== null ? collect([$household]) : collect();
        }

        $user = User::firstWhere('email', strtolower(trim((string) $email)));
        if ($user === null) {
            return collect();
        }

        return $user->ownedHouseholds()->get();
    }
}
