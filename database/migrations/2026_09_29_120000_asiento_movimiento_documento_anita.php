<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factura y OC originales de Anita, por renglón.
 * Un asiento puede mezclar varios comprobantes y varias órdenes de compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asiento_movimiento')) {
            return;
        }

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento_movimiento', 'anita_tipo')) {
                $table->string('anita_tipo', 10)->nullable();
            }
            if (! Schema::hasColumn('asiento_movimiento', 'anita_letra')) {
                $table->string('anita_letra', 3)->nullable();
            }
            if (! Schema::hasColumn('asiento_movimiento', 'anita_sucursal')) {
                $table->unsignedInteger('anita_sucursal')->nullable();
            }
            if (! Schema::hasColumn('asiento_movimiento', 'anita_nro')) {
                $table->unsignedInteger('anita_nro')->nullable();
            }
            if (! Schema::hasColumn('asiento_movimiento', 'nro_ordencompra')) {
                $table->unsignedInteger('nro_ordencompra')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asiento_movimiento')) {
            return;
        }

        Schema::table('asiento_movimiento', function (Blueprint $table) {
            foreach (['nro_ordencompra', 'anita_nro', 'anita_sucursal', 'anita_letra', 'anita_tipo'] as $columna) {
                if (Schema::hasColumn('asiento_movimiento', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
