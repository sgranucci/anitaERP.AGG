<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FK pedido en movimientos de árbol (tipo PE — Pedidos Interforming).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_movimiento')) {
            return;
        }

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('arbolaprobacion_movimiento', 'pedido_id')) {
                $table->unsignedBigInteger('pedido_id')->nullable()->after('ordenventa_id');
                $table->foreign('pedido_id', 'fk_arbol_mov_pedido')
                    ->references('id')->on('pedido')
                    ->onDelete('cascade')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_movimiento')) {
            return;
        }

        Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('arbolaprobacion_movimiento', 'pedido_id')) {
                try {
                    $table->dropForeign('fk_arbol_mov_pedido');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('pedido_id');
            }
        });
    }
};
