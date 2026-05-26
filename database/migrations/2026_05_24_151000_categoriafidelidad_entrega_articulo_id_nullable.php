<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entregas importadas desde clicatent pueden no resolver artículo ERP (SKU Anita sin match).
     */
    public function up(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia') || ! Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'articulo_id')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_categoriafidelidad_entrega_gastronomia_articulo');
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id')->nullable()->change();
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->foreign('articulo_id', 'fk_categoriafidelidad_entrega_gastronomia_articulo')
                ->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categoriafidelidad_entrega_gastronomia') || ! Schema::hasColumn('categoriafidelidad_entrega_gastronomia', 'articulo_id')) {
            return;
        }

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_categoriafidelidad_entrega_gastronomia_articulo');
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id')->nullable(false)->change();
        });

        Schema::table('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->foreign('articulo_id', 'fk_categoriafidelidad_entrega_gastronomia_articulo')
                ->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
