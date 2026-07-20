<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Indumentaria (dotación de ropa de trabajo / EPP), colgado de Sueldos.
 *
 *  - prenda_sueldos: tipo/modelo de prenda (Anita `prenda`: pren_prenda, pren_desc, ...).
 *  - prenda_articulo_sueldos: matriz prenda × color × talle → artículo (Anita `prendart`).
 *    Es el puente con el maestro de stock para descontar existencias en la entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prenda_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();       // Anita pren_prenda
            $table->string('descripcion', 60);                 // Anita pren_desc
            $table->string('marca', 30)->nullable();           // Anita pren_marca
            $table->boolean('es_seguridad')->default(false);   // Anita pren_seguridad (EPP S/N)
            $table->decimal('porcentaje_pedido', 8, 2)->nullable(); // Anita pren_porc_pedido
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['activo', 'orden']);
        });

        Schema::create('prenda_articulo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('color_id');
            $table->unsignedBigInteger('talle_id');
            $table->unsignedBigInteger('articulo_id')->nullable(); // FK maestro stock (ERP)
            $table->string('sku', 20)->nullable();                 // referencia SKU (Anita part_articulo)
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['prenda_id', 'color_id', 'talle_id'], 'prenda_articulo_variante_unique');
            $table->index('articulo_id');
            $table->foreign('prenda_id')->references('id')->on('prenda_sueldos')->onDelete('cascade');
            $table->foreign('color_id')->references('id')->on('color');
            $table->foreign('talle_id')->references('id')->on('talle');
            $table->foreign('articulo_id')->references('id')->on('articulo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prenda_articulo_sueldos');
        Schema::dropIfExists('prenda_sueldos');
    }
};
