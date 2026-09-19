<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\PlanSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'price_monthly',
    'price_yearly',
    'features',
    'limits',
    'is_active',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * ¿Este plan enciende la feature dada? Sin fila = apagada.
     */
    public function hasFeature(PlanFeature|string $feature): bool
    {
        $key = $feature instanceof PlanFeature ? $feature->value : $feature;

        return (bool) ($this->features[$key] ?? false);
    }

    /**
     * Tope numérico para el límite, o null = ilimitado.
     * Un límite que no está en el mapa se interpreta como ilimitado.
     */
    public function limit(PlanLimit|string $limit): ?int
    {
        $key = $limit instanceof PlanLimit ? $limit->value : $limit;

        if (! array_key_exists($key, $this->limits ?? [])) {
            return null;
        }

        $value = $this->limits[$key];

        return $value === null ? null : (int) $value;
    }

    public function planSlug(): PlanSlug
    {
        return PlanSlug::from($this->slug);
    }
}
