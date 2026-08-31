<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una versión de los términos y condiciones (Plan 03, ADR-0031).
 *
 * INMUTABLE por diseño: el contenido nunca se edita — cambiar los términos
 * es publicar una fila nueva. Las aceptaciones referencian la versión y el
 * RESTRICT de su FK impide borrarla una vez alguien la aceptó.
 */
#[Fillable(['version', 'title', 'content', 'change_summary', 'published_at'])]
class TermsVersion extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Binding de ruta por el identificador legible ("2026-09-v1"), no por id:
     * la URL pública es la referencia estable del documento.
     */
    public function getRouteKeyName(): string
    {
        return 'version';
    }

    /**
     * Versión vigente: la publicada más recientemente. Null si nunca se ha
     * publicado nada (en ese caso no hay obligación de aceptación).
     */
    public static function current(): ?self
    {
        return static::query()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * Aceptaciones registradas sobre esta versión.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(UserTermsAcceptance::class);
    }
}
