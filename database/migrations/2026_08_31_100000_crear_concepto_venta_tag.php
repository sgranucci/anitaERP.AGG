<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags pedibles en la plantilla de descripción ARCA del concepto de mostrador.
 * Sintaxis: @clave@ — al facturar se completan por modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_venta_tag', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_venta_id');
            $table->string('clave', 40);
            $table->string('etiqueta', 80);
            $table->string('tipo', 20)->default('texto');
            $table->boolean('obligatorio')->default(true);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->unsignedSmallInteger('largo_max')->nullable();
            $table->timestamps();

            $table->unique(['concepto_venta_id', 'clave'], 'uk_concepto_venta_tag_clave');
            $table->index(['concepto_venta_id', 'orden'], 'idx_concepto_venta_tag_orden');

            $table->foreign('concepto_venta_id', 'fk_concepto_venta_tag_concepto')
                ->references('id')->on('concepto_venta')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_venta_tag');
    }
};
