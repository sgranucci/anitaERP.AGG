<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Línea de mostrador sin artículo y default del tipo (como tcomp_concepto de Anita).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('concepto_venta_id')->nullable()->after('articulo_id');
            $table->foreign('concepto_venta_id', 'fk_venta_emision_concepto_venta')
                ->references('id')->on('concepto_venta')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->unsignedBigInteger('concepto_ordenventa_id')->nullable()->after('concepto_venta_id');
            $table->foreign('concepto_ordenventa_id', 'fk_venta_emision_concepto_ordenventa')
                ->references('id')->on('concepto_ordenventa')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::table('tipotransaccion', function (Blueprint $table) {
            $table->unsignedBigInteger('concepto_venta_id')->nullable()->after('estado');
            $table->foreign('concepto_venta_id', 'fk_tipotransaccion_concepto_venta')
                ->references('id')->on('concepto_venta')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('venta_emision', function (Blueprint $table) {
            $table->dropForeign('fk_venta_emision_concepto_ordenventa');
            $table->dropColumn('concepto_ordenventa_id');
            $table->dropForeign('fk_venta_emision_concepto_venta');
            $table->dropColumn('concepto_venta_id');
        });

        Schema::table('tipotransaccion', function (Blueprint $table) {
            $table->dropForeign('fk_tipotransaccion_concepto_venta');
            $table->dropColumn('concepto_venta_id');
        });
    }
};
