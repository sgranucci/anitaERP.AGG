<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurisdicciones CM05 del proveedor (inscripción por provincia).
 * Disponible en todos los entornos. En Crown alimenta la matriz de retención IIBB.
 * Idempotente: si ya existe (p. ej. migración Crown previa), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_cm05')) {
            return;
        }

        Schema::create('proveedor_cm05', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_cm05_proveedor')
                ->references('id')->on('proveedor')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_proveedor_cm05_provincia')
                ->references('id')->on('provincia')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->date('fechavigencia')->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'provincia_id'], 'uk_proveedor_cm05_prov');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_cm05');
    }
};
