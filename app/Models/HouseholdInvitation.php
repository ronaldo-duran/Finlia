<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use Database\Factories\HouseholdInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 */
#[Fillable(['household_id', 'email', 'token', 'role', 'status', 'expires_at'])]
class HouseholdInvitation extends Model
{
    /** @use HasFactory<HouseholdInvitationFactory> */
    use HasFactory;

    protected $casts = [
        'role' => HouseholdRole::class,
        'status' => InvitationStatus::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
