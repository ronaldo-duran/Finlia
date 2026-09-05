<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Plan 06 (ADR-0034): export asíncrono vía correo.
            // null = sin solicitud pendiente; timestamp = exportación en cola.
            // El cron lo limpia a null una vez enviado el correo.
            $table->timestamp('data_export_requested_at')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('data_export_requested_at');
        });
    }
};
