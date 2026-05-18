<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * articulo_movimiento.loteimportacion_id debe ser NULL cuando no hay lote (FK a lote).
     * La migración de creación ya lo definía nullable; en algunas bases quedó NOT NULL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('articulo_movimiento') || ! Schema::hasColumn('articulo_movimiento', 'loteimportacion_id')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_movimiento_lote');
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('loteimportacion_id')->nullable()->change();
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->foreign('loteimportacion_id', 'fk_articulo_movimiento_lote')
                ->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_movimiento') || ! Schema::hasColumn('articulo_movimiento', 'loteimportacion_id')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_movimiento_lote');
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('loteimportacion_id')->nullable(false)->change();
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->foreign('loteimportacion_id', 'fk_articulo_movimiento_lote')
                ->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
