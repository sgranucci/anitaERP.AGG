<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito opt-in de aprobación de artículos (tipo AR).
 * - FK articulo_id en movimientos del árbol
 * - Flags por uso: modo auto/arbol + árbol específico
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'articulo_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('articulo_id')->nullable()->after('propuesta_pago_id');
                $table->foreign('articulo_id', 'fk_arbol_mov_articulo')
                    ->references('id')->on('articulo')
                    ->onDelete('cascade')->onUpdate('cascade');
            });
        }

        if (Schema::hasTable('usoarticulo')) {
            Schema::table('usoarticulo', function (Blueprint $table) {
                if (! Schema::hasColumn('usoarticulo', 'aprobacion_modo')) {
                    // auto | arbol | default  (default = árbol Artículos genérico / Contaduría)
                    $table->string('aprobacion_modo', 20)->default('default')->after('nombre');
                }
                if (! Schema::hasColumn('usoarticulo', 'arbolaprobacion_id')) {
                    $table->unsignedBigInteger('arbolaprobacion_id')->nullable()->after('aprobacion_modo');
                    $table->foreign('arbolaprobacion_id', 'fk_usoarticulo_arbol')
                        ->references('id')->on('arbolaprobacion')
                        ->onDelete('set null')->onUpdate('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usoarticulo')) {
            Schema::table('usoarticulo', function (Blueprint $table) {
                if (Schema::hasColumn('usoarticulo', 'arbolaprobacion_id')) {
                    try {
                        $table->dropForeign('fk_usoarticulo_arbol');
                    } catch (\Throwable $e) {
                    }
                    $table->dropColumn('arbolaprobacion_id');
                }
                if (Schema::hasColumn('usoarticulo', 'aprobacion_modo')) {
                    $table->dropColumn('aprobacion_modo');
                }
            });
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'articulo_id')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_arbol_mov_articulo');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('articulo_id');
            });
        }
    }
};
