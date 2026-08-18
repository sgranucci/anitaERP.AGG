<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        MigrationDialectSupport::statementPorDriver(
            "UPDATE articulo_movimiento am
             JOIN vianda_consumo vc ON am.concepto LIKE CONCAT('Vianda ', vc.codigo_retiro, '%')
             SET am.vianda_consumo_id = vc.id
             WHERE am.vianda_consumo_id IS NULL",
            "UPDATE articulo_movimiento AS am
             SET vianda_consumo_id = vc.id
             FROM vianda_consumo AS vc
             WHERE am.concepto LIKE ('Vianda ' || vc.codigo_retiro || '%')
               AND am.vianda_consumo_id IS NULL"
        );

        // Índice + FK (set null): agregar solo si no existen para poder re-ejecutar la migración.
        if (! MigrationDialectSupport::tieneIndice('articulo_movimiento', 'ix_artmov_vianda_consumo')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->index('vianda_consumo_id', 'ix_artmov_vianda_consumo');
            });
        }

        if (! MigrationDialectSupport::tieneForeignKey('articulo_movimiento', 'fk_artmov_vianda_consumo')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->foreign('vianda_consumo_id', 'fk_artmov_vianda_consumo')
                    ->references('id')->on('vianda_consumo')
                    ->nullOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (MigrationDialectSupport::tieneForeignKey('articulo_movimiento', 'fk_artmov_vianda_consumo')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_artmov_vianda_consumo');
            });
        }

        if (MigrationDialectSupport::tieneIndice('articulo_movimiento', 'ix_artmov_vianda_consumo')) {
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
