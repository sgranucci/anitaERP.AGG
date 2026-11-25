<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Errores / omisiones de una corrida de liquidación, por empleado.
 * Se regenera en cada Calcular/Recalcular para depurar sin depender del flash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_error_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('empleado_id')->nullable();
            $table->unsignedInteger('legajo')->nullable();
            $table->string('apellido_nombre', 120)->nullable();
            $table->unsignedInteger('concepto_codigo')->nullable();
            $table->string('concepto_descripcion', 80)->nullable();
            // formula | empleado | recibo | omitido
            $table->string('etapa', 20)->default('formula');
            $table->text('mensaje');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['liquidacion_id', 'etapa'], 'liqerr_liq_etapa_idx');
            $table->index(['liquidacion_id', 'empleado_id'], 'liqerr_liq_emp_idx');
            $table->index(['liquidacion_id', 'legajo'], 'liqerr_liq_legajo_idx');

            $table->foreign('liquidacion_id', 'liqerr_liq_fk')
                ->references('id')->on('liquidacion_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_error_sueldos');
    }
};
