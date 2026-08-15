<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'name', 'type', 'color', 'icon', 'is_default'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_default' => 'boolean',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * ¿Categoría global (compartida por seed)? household_id NULL.
     */
    public function isGlobal(): bool
    {
        return $this->household_id === null;
    }

    /**
     * Scope: categorías visibles para un hogar (las globales + las propias).
     */
    public function scopeForHousehold(Builder $query, int $householdId): Builder
    {
        return $query->where(static function (Builder $q) use ($householdId): void {
            $q->whereNull('household_id')->orWhere('household_id', $householdId);
        });
    }
}
