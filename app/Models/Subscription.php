<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_id',
    'status',
    'started_at',
    'renews_at',
    'ends_at',
    'canceled_at',
    'reason',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * ¿La suscripción está "vigente" hoy? Un estado que otorga plan Y sin
     * `ends_at` en el pasado. La zona horaria del hogar no importa aquí:
     * `ends_at` es momento absoluto.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->status->grantsPlan()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
