<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo usado al cerrar el recuento: FECHA_RECUENTO | SALDO_ACTUAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recuento') || Schema::hasColumn('recuento', 'modo_cierre')) {
            return;
        }

        Schema::table('recuento', function (Blueprint $table) {
            $table->string('modo_cierre', 20)->nullable()->after('movimientostock_anulacion_id')
                ->comment('FECHA_RECUENTO|SALDO_ACTUAL al procesar cierre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recuento') || ! Schema::hasColumn('recuento', 'modo_cierre')) {
            return;
        }

        Schema::table('recuento', function (Blueprint $table) {
            $table->dropColumn('modo_cierre');
        });
    }
};
