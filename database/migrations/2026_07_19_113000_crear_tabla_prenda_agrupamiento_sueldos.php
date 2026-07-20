<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dotación de indumentaria por agrupamiento y sexo (Anita `prendxagr`).
 * Define qué prendas corresponden a cada agrupamiento según el sexo, con el
 * tope anual de entregas y (opcional) el color asignado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prenda_agrupamiento_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agrupamiento_id');      // Anita prxagr_cod_agrup (mapeado a id)
            $table->char('sexo', 1);                            // '1' masculino, '2' femenino (empleado.sexo)
            $table->unsignedInteger('orden')->default(0);       // Anita prxagr_orden
            $table->unsignedBigInteger('prenda_id');            // Anita prxagr_prenda (mapeado a id)
            $table->unsignedBigInteger('color_id')->nullable(); // Anita prxagr_color (mapeado a id)
            $table->decimal('limite_anual', 8, 2)->default(0);  // Anita prxagr_lim_anual
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['agrupamiento_id', 'sexo', 'prenda_id', 'color_id'], 'prenda_agr_variante_unique');
            $table->index(['agrupamiento_id', 'sexo']);
            $table->foreign('agrupamiento_id')->references('id')->on('agrupamiento_sueldos')->onDelete('cascade');
            $table->foreign('prenda_id')->references('id')->on('prenda_sueldos')->onDelete('cascade');
            $table->foreign('color_id')->references('id')->on('color')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prenda_agrupamiento_sueldos');
    }
};
