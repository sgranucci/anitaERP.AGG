<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fórmula genérica: artículo de cabecera opcional (varios artículos pueden apuntar vía articulo.formula).
     */
    public function up(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->dropForeign('fk_formula_articulo_articulo');
        });
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id')->nullable()->change();
        });
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->foreign('articulo_id', 'fk_formula_articulo_articulo')
                ->references('id')->on('articulo')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->dropForeign('fk_formula_articulo_articulo');
        });
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('articulo_id')->nullable(false)->change();
        });
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->foreign('articulo_id', 'fk_formula_articulo_articulo')
                ->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
        });
    }
};
