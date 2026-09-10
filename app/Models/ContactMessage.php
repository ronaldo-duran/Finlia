<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactReason;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje de contacto: alianzas, comercial, sugerencia o reporte de error.
 *
 * Campos que NO son asignables en masa, porque los pone el servidor y no
 * puede decidirlos quien envía el formulario:
 *   - user_id    → del usuario autenticado, si lo hay
 *   - ip_address → de la petición, y solo en envíos anónimos
 *   - context    → capturado del navegador y de la app, no del formulario
 */
#[Fillable(['reason', 'name', 'email', 'body'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => ContactReason::class,
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFromGuest(): bool
    {
        return $this->user_id === null;
    }
}
