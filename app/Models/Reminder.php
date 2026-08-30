<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\ReminderStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recordatorio suelto del hogar (Épica 9, ADR-0027): tecnomecánica,
 * renovación de pasaporte, una obligación anual sin cuenta asociada…
 *
 * Los recordatorios de recurrentes, deudas y metas NO viven aquí: se
 * derivan de su fuente en ReminderService. Solo el usuario crea filas
 * de esta tabla.
 */
#[Fillable(['title', 'amount', 'due_date', 'frequency', 'status', 'notes'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * household_id NO es fillable: lo asigna el controlador desde el hogar
     * activo. En `status` solo se persisten pending|completed (ADR-0027):
     * vencido/próximo se resuelven contra hoy en ReminderStatus::resolve().
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => Frequency::class,
            'status' => ReminderStatus::class,
            // date:Y-m-d — sin él, el grammar serializa con hora y SQLite
            // guarda "2026-09-05 00:00:00" en una columna DATE.
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Scope: los que siguen vivos (aún sin atender).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReminderStatus::Pending->value);
    }

    public function isCompleted(): bool
    {
        return $this->status === ReminderStatus::Completed;
    }

    /**
     * Estado efectivo contra hoy: el persistido + la ventana temporal.
     */
    public function effectiveStatus(?CarbonInterface $today = null): ReminderStatus
    {
        if ($this->isCompleted() || $this->due_date === null) {
            return ReminderStatus::Completed;
        }

        return ReminderStatus::resolve(Carbon::parse($this->due_date), $today);
    }
}
