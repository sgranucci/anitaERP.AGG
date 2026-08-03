<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Novedades de liquidación (Anita: novedad).
 * Entrada de período/corrida que consume el motor via novedad()/novedad2()/novedad_hist().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedad_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedInteger('concepto_codigo'); // denormalizado Anita / V(n)
            $table->decimal('valor1', 18, 4)->default(0); // V() cantidad/importe principal
            $table->decimal('valor2', 18, 4)->default(0); // P() valor secundario
            $table->string('estado', 20)->default('pendiente'); // pendiente|incluida|anulada
            $table->date('fecha_vto')->nullable();
            $table->unsignedInteger('nro_interno')->default(0);
            $table->unsignedInteger('periodo')->nullable(); // YYYYMM (histórico / sync)
            $table->string('origen', 30)->default('manual');
            // manual|import|ausencia|reloj|plan_cuota|sync_anita
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['liquidacion_id', 'empleado_id', 'concepto_id'], 'novedad_liq_emp_conc_idx');
            $table->index(['empresa_id', 'empleado_id', 'concepto_codigo', 'periodo'], 'novedad_emp_conc_per_idx');
            $table->index(['empleado_id', 'estado'], 'novedad_emp_estado_idx');
            $table->index(['concepto_codigo', 'periodo'], 'novedad_conc_per_idx');

            $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            $table->foreign('liquidacion_id')->references('id')->on('liquidacion_sueldos')->nullOnDelete();
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->cascadeOnDelete();
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedad_sueldos');
    }
};
