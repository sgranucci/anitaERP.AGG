<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relación dura entre los movimientos de stock y la vianda que los generó.
 * Antes se vinculaban solo por el texto del concepto ("Vianda {codigo}…"); ahora
 * articulo_movimiento.vianda_consumo_id apunta al consumo, permitiendo reversar el
 * stock por id (borrado) y reportar movimientos por join directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('articulo_movimiento', 'vianda_consumo_id')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('vianda_consumo_id')->nullable()->after('venta_emision_id');
            });
        }

        // Backfill de los movimientos ya generados por viandas (relación por concepto único
        // "Vianda {codigo_retiro}…"). vianda_consumo tiene pocas filas, así que es un único scan.
        DB::statement(
            'UPDATE articulo_movimiento am '
            ."JOIN vianda_consumo vc ON am.concepto LIKE CONCAT('Vianda ', vc.codigo_retiro, '%') "
            .'SET am.vianda_consumo_id = vc.id '
            .'WHERE am.vianda_consumo_id IS NULL'
        );

        // Índice + FK (set null): agregar solo si no existen para poder re-ejecutar la migración.
        $indices = collect(DB::select("SHOW INDEX FROM articulo_movimiento WHERE Key_name = 'ix_artmov_vianda_consumo'"));
        if ($indices->isEmpty()) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->index('vianda_consumo_id', 'ix_artmov_vianda_consumo');
            });
        }

        $fks = collect(DB::select(
            "SELECT constraint_name FROM information_schema.table_constraints "
            ."WHERE table_schema = DATABASE() AND table_name = 'articulo_movimiento' "
            ."AND constraint_name = 'fk_artmov_vianda_consumo'"
        ));
        if ($fks->isEmpty()) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->foreign('vianda_consumo_id', 'fk_artmov_vianda_consumo')
                    ->references('id')->on('vianda_consumo')
                    ->nullOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        $fks = collect(DB::select(
            "SELECT constraint_name FROM information_schema.table_constraints "
            ."WHERE table_schema = DATABASE() AND table_name = 'articulo_movimiento' "
            ."AND constraint_name = 'fk_artmov_vianda_consumo'"
        ));
        if ($fks->isNotEmpty()) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_artmov_vianda_consumo');
            });
        }

        $indices = collect(DB::select("SHOW INDEX FROM articulo_movimiento WHERE Key_name = 'ix_artmov_vianda_consumo'"));
        if ($indices->isNotEmpty()) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropIndex('ix_artmov_vianda_consumo');
            });
        }

        if (Schema::hasColumn('articulo_movimiento', 'vianda_consumo_id')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropColumn('vianda_consumo_id');
            });
        }
    }
};
