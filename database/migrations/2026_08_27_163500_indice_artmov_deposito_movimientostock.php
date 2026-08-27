<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listado por empresa: parte de articulo_movimiento filtrado por depósito.
 * (deposito_id, movimientostock_id) cubre el IN de depósitos sin barrer 1M filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_movimiento') && ! Schema::hasIndex('articulo_movimiento', 'idx_artmov_deposito_ms')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->index(['deposito_id', 'movimientostock_id'], 'idx_artmov_deposito_ms');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('articulo_movimiento') && Schema::hasIndex('articulo_movimiento', 'idx_artmov_deposito_ms')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropIndex('idx_artmov_deposito_ms');
            });
        }
    }
};
