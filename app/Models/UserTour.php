<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de que un usuario ya vio una guía de pantalla (ADR-0045).
 */
#[Fillable(['key', 'version', 'status', 'seen_at'])]
class UserTour extends Model
{
    /** user_id NO es fillable: sale del usuario autenticado, nunca de la petición. */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => TourStatus::class,
            'seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
