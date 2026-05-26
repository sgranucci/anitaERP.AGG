<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entregas importadas desde clicatent pueden referenciar facturas Anita aún no replicadas en venta.
     */
    public function up(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia') || ! Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'venta_id')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_categoriafidelidad_entrega_gastronomia_venta');
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->nullable()->change();
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->foreign('venta_id', 'fk_categoriafidelidad_entrega_gastronomia_venta')
                ->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia') || ! Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'venta_id')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_categoriafidelidad_entrega_gastronomia_venta');
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->nullable(false)->change();
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->foreign('venta_id', 'fk_categoriafidelidad_entrega_gastronomia_venta')
                ->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
