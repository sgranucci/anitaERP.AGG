<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * asiento_movimiento.asiento_id: CASCADE → RESTRICT.
 *
 * Obliga a borrar cada línea vía Eloquent (AsientoObserver → Asiento_MovimientoObserver)
 * antes de eliminar el asiento, para mantener cuentacontable_saldo_mes consistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asiento_movimiento')) {
            return;
        }

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_asiento_movimiento_asiento');
        });

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            $table->foreign('asiento_id', 'fk_asiento_movimiento_asiento')
                ->references('id')->on('asiento')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asiento_movimiento')) {
            return;
        }

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_asiento_movimiento_asiento');
        });

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            $table->foreign('asiento_id', 'fk_asiento_movimiento_asiento')
                ->references('id')->on('asiento')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
};
