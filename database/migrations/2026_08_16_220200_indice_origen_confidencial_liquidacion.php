<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clave natural idempotente del detalle confidencial importado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidacion_detalle_sueldos', function (Blueprint $table) {
            $table->unique('origen_clave', 'liqdetalle_origen_clave_uq');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('liquidacion_detalle_sueldos', function (Blueprint $table) {
                $table->dropUnique('liqdetalle_origen_clave_uq');
            });
        } catch (\Throwable) {
            // El índice puede no existir en instalaciones parciales.
        }
    }
};
