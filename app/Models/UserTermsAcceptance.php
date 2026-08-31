<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de que un usuario aceptó una versión concreta de los términos
 * (Plan 03): versión exacta, fecha y IP. Solo se crea a través de
 * User::acceptTerms(), que la hace idempotente.
 */
#[Fillable(['terms_version_id', 'accepted_at', 'ip_address'])]
class UserTermsAcceptance extends Model
{
    /** user_id NO es fillable: sale del usuario autenticado, nunca de la petición. */
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }
}
