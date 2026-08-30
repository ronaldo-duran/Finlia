<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correo pendiente de confirmación (Plan 02, ADR-0030).
     *
     * El cambio de correo exige doble confirmación: nada entra a
     * users.email sin pasar por una bandeja verificada. El token se
     * guarda HASHEADO (sha256) — un volcado de la base no revela los
     * enlaces válidos (patrón de household_invitations).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('pending_email_token', 64)->nullable()->after('pending_email');
            $table->timestamp('pending_email_requested_at')->nullable()->after('pending_email_token');

            // La confirmación busca por token hasheado; es la única query
            // por estas columnas.
            $table->index('pending_email_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['pending_email_token']);
            $table->dropColumn(['pending_email', 'pending_email_token', 'pending_email_requested_at']);
        });
    }
};
