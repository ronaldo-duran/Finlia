<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aclara el compromiso de pago de una deuda (ADR-0022).
     *
     * `scheduled_payment` ("cuota pactada") mentía: no es lo que pacta el
     * banco, es lo que el usuario decide pagar cada mes. Pasa a llamarse
     * `planned_payment`.
     *
     * Y se añade `term_months`: uno no pacta una fecha de fin, pacta un
     * número de cuotas. `end_date` se conserva, pero pasa a ser derivada
     * (inicio + cuotas) en lugar de un campo que el usuario teclea.
     */
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->renameColumn('scheduled_payment', 'planned_payment');
        });

        Schema::table('debts', function (Blueprint $table) {
            // Número de cuotas pactadas. El tope depende del tipo de deuda
            // (ver App\Enums\DebtType::maxTermMonths()).
            $table->unsignedSmallInteger('term_months')->nullable()->after('planned_payment');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('term_months');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->renameColumn('planned_payment', 'scheduled_payment');
        });
    }
};
