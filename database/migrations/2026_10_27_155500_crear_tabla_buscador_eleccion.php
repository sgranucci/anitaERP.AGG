<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria del buscador del encabezado: qué fila abre cada rol para un texto.
 * El ranking siguiente sube esa fila. No guarda el padrón ni los comprobantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('buscador_eleccion')) {
            return;
        }

        Schema::create('buscador_eleccion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rol_id');
            $table->string('consulta', 80);
            $table->string('tipo', 20);
            $table->string('clave', 190);
            $table->unsignedInteger('veces')->default(1);
            $table->timestamps();
            $table->unique(['rol_id', 'consulta', 'clave'], 'uk_buscador_eleccion');
            $table->index(['rol_id', 'consulta'], 'idx_buscador_eleccion_consulta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buscador_eleccion');
    }
};
