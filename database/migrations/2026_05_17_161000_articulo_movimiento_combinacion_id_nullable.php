<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insumos gastronomía / artículos sin talle no tienen combinación (FK opcional).
     */
    public function up(): void
    {
        if (! Schema::hasTable('articulo_movimiento') || ! Schema::hasColumn('articulo_movimiento', 'combinacion_id')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_movimiento_combinacion');
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('combinacion_id')->nullable()->change();
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->foreign('combinacion_id', 'fk_articulo_movimiento_combinacion')
                ->references('id')->on('combinacion')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_movimiento') || ! Schema::hasColumn('articulo_movimiento', 'combinacion_id')) {
            return;
        }

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_movimiento_combinacion');
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('combinacion_id')->nullable(false)->change();
        });

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            $table->foreign('combinacion_id', 'fk_articulo_movimiento_combinacion')
                ->references('id')->on('combinacion')->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
