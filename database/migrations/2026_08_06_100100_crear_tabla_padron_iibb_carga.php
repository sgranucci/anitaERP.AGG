<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada importación de padrón IIBB.
 *
 * Le da al usuario final una respuesta inmediata en pantalla ("qué se cargó,
 * cuándo, quién y cómo salió") sin tener que agrupar millones de filas de
 * padron_iibb_tasa ni depender del mail de resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padron_iibb_carga', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_padron_iibb_carga_provincia')
                ->references('id')->on('provincia')
                ->onDelete('set null')->onUpdate('cascade');
            $table->unsignedInteger('jurisdiccion')->nullable();
            $table->string('etiqueta', 100);
            $table->string('tipopadron', 10)->nullable();
            $table->string('origen', 20)->default('pantalla');
            $table->string('estado', 20)->default('en_proceso');
            $table->string('archivo', 500)->nullable();
            $table->date('desdefecha')->nullable();
            $table->date('hastafecha')->nullable();
            $table->unsignedBigInteger('filas_leidas')->default(0);
            $table->unsignedBigInteger('filas_insertadas')->default(0);
            $table->unsignedBigInteger('filas_actualizadas')->default(0);
            $table->unsignedBigInteger('filas_omitidas')->default(0);
            $table->unsignedBigInteger('filas_borradas')->default(0);
            $table->unsignedBigInteger('errores')->default(0);
            $table->unsignedInteger('segundos')->nullable();
            $table->text('mensaje')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['provincia_id', 'created_at'], 'padron_iibb_carga_provincia_fecha_index');
            $table->index('estado', 'padron_iibb_carga_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padron_iibb_carga');
    }
};
