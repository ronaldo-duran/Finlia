<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot de membresía usuario ↔ hogar.
 * Permite castear el rol a enum en lecturas del pivote.
 */
class HouseholdMember extends Pivot
{
    protected $casts = [
        'role' => HouseholdRole::class,
        'joined_at' => 'datetime',
        // Preferencias de recordatorios por miembro (Épica 9, ADR-0028).
        'reminders_email' => 'boolean',
        'last_reminder_digest_at' => 'datetime',
    ];
}
