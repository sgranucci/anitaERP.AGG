<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera Stock por OT / saldos por lote (WHERE lote > 0 OR ordentrabajo_id > 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulo_movimiento')) {
            return;
        }

        try {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->index('lote', 'idx_articulo_movimiento_lote');
            });
        } catch (\Throwable $e) {
            // Índice ya existe: no fallar el migrate.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_movimiento')) {
            return;
        }

        try {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropIndex('idx_articulo_movimiento_lote');
            });
        } catch (\Throwable $e) {
            //
        }
    }
};
