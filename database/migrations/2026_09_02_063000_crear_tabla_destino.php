<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro Anita `destino` (destino.sql):
 *   dest_destino, dest_localidad, dest_provincia, dest_pais,
 *   dest_patagonico, dest_cod_localidad
 *
 * Clave = código de zona de venta. dest_cod_localidad = se:localidad SENASA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('destino')) {
            return;
        }

        Schema::create('destino', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->comment('dest_destino = zonavta.codigo Anita (zonv_codigo), nunca zonavta.id');
            $table->unsignedBigInteger('zonavta_id')->nullable();
            $table->string('localidad', 80)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->unsignedInteger('pais_codigo')->nullable()->comment('dest_pais (código Anita)');
            $table->boolean('patagonico')->default(false);
            $table->unsignedInteger('codigo_localidad_senasa')->nullable()->comment('dest_cod_localidad');
            $table->timestamps();

            $table->unique('codigo');
            $table->index('localidad');
            $table->foreign('zonavta_id', 'fk_destino_zonavta')
                ->references('id')->on('zonavta')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destino');
    }
};
