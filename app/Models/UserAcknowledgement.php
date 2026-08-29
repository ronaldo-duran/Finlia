<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcknowledgementKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de que un usuario leyó un aviso (ADR-0024).
 */
#[Fillable(['key', 'acknowledged_at'])]
class UserAcknowledgement extends Model
{
    /** user_id NO es fillable: sale del usuario autenticado, nunca de la petición. */
    protected function casts(): array
    {
        return [
            'key' => AcknowledgementKey::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
