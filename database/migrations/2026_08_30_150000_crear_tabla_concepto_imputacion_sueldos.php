<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Mapeo contable de conceptos de liquidación (fase 0 asiento de sueldos).
 * Una fila por empresa + alcance + clave. Sin SoftDeletes.
 *
 * alcance:
 *   concepto → override de un concepto_sueldos
 *   rubro    → RubroCostoLaboral (contribuciones / aportes)
 *   tipo     → ConceptoTipo imputable (fallback)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_imputacion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('alcance', 20);
            $table->string('clave', 64);
            $table->unsignedBigInteger('concepto_id')->nullable();
            $table->string('rubro', 32)->nullable();
            $table->string('tipo', 30)->nullable();
            $table->unsignedBigInteger('cuenta_debe_id')->nullable();
            $table->unsignedBigInteger('cuenta_haber_id')->nullable();
            $table->string('observacion', 160)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'alcance', 'clave'], 'imputacion_sueldos_empresa_alcance_clave_uq');
            $table->index(['empresa_id', 'alcance']);
            $table->index('concepto_id');

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('restrict');
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->onDelete('restrict');
            $table->foreign('cuenta_debe_id')->references('id')->on('cuentacontable')->onDelete('restrict');
            $table->foreign('cuenta_haber_id')->references('id')->on('cuentacontable')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_imputacion_sueldos');
    }
};
