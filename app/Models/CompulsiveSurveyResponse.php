<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'expense_id',
    'planned',
    'kind',
    'mood',
    'trigger',
])]
class CompulsiveSurveyResponse extends Model
{
    protected function casts(): array
    {
        return [
            'planned' => CompulsivePlanned::class,
            'kind' => CompulsiveKind::class,
            'mood' => CompulsiveMood::class,
            'trigger' => CompulsiveTrigger::class,
            'follow_up_due_at' => 'datetime',
            'follow_up_answered_at' => 'datetime',
            'mood_after' => CompulsiveMood::class,
            'follow_up_regret' => 'boolean',
        ];
    }

    /**
     * Respuestas cuya cita de seguimiento ya venció y aún no contestaron.
     */
    public function scopePendingFollowUp(Builder $query): Builder
    {
        return $query
            ->whereNotNull('follow_up_due_at')
            ->where('follow_up_due_at', '<=', now())
            ->whereNull('follow_up_answered_at');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
