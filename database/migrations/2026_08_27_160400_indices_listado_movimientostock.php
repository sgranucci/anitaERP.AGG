<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera el listado unificado al filtrar por empresa/depósito
 * (EXISTS articulo_movimiento + transferencias por empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_movimiento') && ! Schema::hasIndex('articulo_movimiento', 'idx_artmov_ms_deposito')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->index(['movimientostock_id', 'deposito_id'], 'idx_artmov_ms_deposito');
            });
        }

        if (Schema::hasTable('transferencia_mercaderia') && ! Schema::hasIndex('transferencia_mercaderia', 'idx_tm_empresa_fecha')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->index(['empresa_id', 'fecha'], 'idx_tm_empresa_fecha');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('articulo_movimiento') && Schema::hasIndex('articulo_movimiento', 'idx_artmov_ms_deposito')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropIndex('idx_artmov_ms_deposito');
            });
        }

        if (Schema::hasTable('transferencia_mercaderia') && Schema::hasIndex('transferencia_mercaderia', 'idx_tm_empresa_fecha')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->dropIndex('idx_tm_empresa_fecha');
            });
        }
    }
};
