<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Estado de un recordatorio (Épica 9).
 *
 * Solo `pending` y `completed` se persisten: `upcoming` y `overdue` se
 * RESUELVEN contra hoy (resolve()), así un recordatorio nunca queda con
 * un estado caducado porque el cron no haya corrido — espejo de la
 * filosofía de saldos derivados (ADR-0020/ADR-0025).
 */
enum ReminderStatus: string
{
    case Pending = 'pending';
    case Upcoming = 'upcoming';
    case Overdue = 'overdue';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Upcoming => 'Próximo',
            self::Overdue => 'Vencido',
            self::Completed => 'Completado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-light text-muted',
            self::Upcoming => 'text-bg-warning',
            self::Overdue => 'text-bg-danger',
            self::Completed => 'text-bg-success',
        };
    }

    /**
     * Estado efectivo de una fecha de vencimiento: vencida si ya pasó,
     * próxima si cae dentro de la ventana de aviso, pendiente si queda
     * más lejos. `completed` nunca se deriva: es una acción del usuario.
     */
    public static function resolve(CarbonInterface $dueDate, ?CarbonInterface $today = null, int $upcomingDays = 7): self
    {
        $today = ($today !== null ? Carbon::parse($today) : Carbon::now(config('app.timezone')))->startOfDay();
        $due = Carbon::parse($dueDate)->startOfDay();

        if ($due->isBefore($today)) {
            return self::Overdue;
        }

        return $due->lessThanOrEqualTo($today->copy()->addDays($upcomingDays))
            ? self::Upcoming
            : self::Pending;
    }
}
