<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias de recordatorios por miembro (Épica 9, ADR-0028):
     * el digest diario por correo es OPT-IN y la marca de último envío
     * hace idempotente el comando del Scheduler.
     */
    public function up(): void
    {
        Schema::table('household_user', function (Blueprint $table) {
            $table->boolean('reminders_email')->default(false)->after('joined_at');
            $table->timestamp('last_reminder_digest_at')->nullable()->after('reminders_email');
            $table->index('reminders_email');
        });
    }

    public function down(): void
    {
        Schema::table('household_user', function (Blueprint $table) {
            $table->dropIndex(['reminders_email']);
            $table->dropColumn(['reminders_email', 'last_reminder_digest_at']);
        });
    }
};
