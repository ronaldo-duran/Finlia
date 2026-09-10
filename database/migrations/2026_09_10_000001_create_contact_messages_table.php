<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes de contacto: alianzas, comercial, sugerencias y reportes de error.
 *
 * Una sola tabla para las dos entradas —el formulario público y el reporte de
 * error desde dentro de la aplicación— porque el dato es el mismo y lo único
 * que cambia es cuánto se sabe de quien escribe.
 *
 * No lleva `household_id`: un mensaje de contacto no es un dato financiero del
 * hogar, es una conversación con el responsable del producto. Por eso tampoco
 * tiene Policy de hogar ni aparece en la exportación de datos del hogar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            $table->string('reason', 20)
                ->comment('App\Enums\ContactReason');

            $table->string('name', 120);
            $table->string('email', 255);
            $table->text('body');

            // Nulo cuando escribe alguien sin cuenta desde el sitio público.
            $table->foreignId('user_id')
                ->nullable()
                ->comment('Autor, si tenía sesión iniciada')
                ->constrained()
                ->nullOnDelete();

            // Solo se guarda en envíos ANÓNIMOS, donde es el único rastro para
            // frenar abuso. Con sesión iniciada basta user_id, y guardarla
            // además sería recolectar de más (Ley 1581, minimización).
            $table->string('ip_address', 45)
                ->nullable()
                ->comment('Solo en envíos sin sesión');

            // Contexto técnico de un reporte de error: versión, ruta desde la
            // que se reportó, navegador y viewport. Se captura solo, para que
            // "no me funciona" llegue siendo accionable.
            $table->json('context')->nullable();

            $table->timestamps();

            // Listado por motivo y fecha, que es como se leen.
            $table->index(['reason', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
