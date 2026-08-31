<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de consentimiento de términos por usuario y versión (Plan 03).
     *
     * Es la prueba ante un reclamo o auditoría: quién, qué versión exacta,
     * cuándo y desde qué IP. La IP es dato personal (Ley 1581) y se guarda
     * con finalidad exclusiva de prueba de aceptación (Plan 03, ADR-0031);
     * nullable para no obligar a tenerla.
     *
     * No reutiliza user_acknowledgements (ADR-0024): esos avisos son por
     * clave de enum y mutables; los términos exigen versión con contenido
     * histórico inmutable.
     */
    public function up(): void
    {
        Schema::create('user_terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // RESTRICT: una versión con aceptaciones no se puede borrar —
            // el historial de consentimiento es intocable por diseño.
            $table->foreignId('terms_version_id')
                ->constrained('terms_versions')
                ->restrictOnDelete();
            $table->timestamp('accepted_at');
            // IPv4/IPv6 (45 es el máximo de IPv6 textual).
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Una aceptación por usuario y versión: idempotente (patrón de
            // user_acknowledgements, ADR-0024).
            $table->unique(['user_id', 'terms_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_terms_acceptances');
    }
};
