<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Historico de acumuladores por empleado y periodo. Es la memoria "hacia atras"
 * del motor: al calcular una corrida se guarda, por cada empleado, el valor de
 * cada acumulador (REM, NOREM, DESC...) del periodo. Permite calcular:
 *   - SAC: mejor remuneracion mensual del semestre (Ley 27.073).
 *   - Proporcionales y liquidacion final (mejor remuneracion normal/habitual).
 *   - Topes y promedios multi-periodo (Ganancias 4a, vacaciones).
 *
 * Se lee en las formulas con acum_hist() / mejor_rem_semestre().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_acumulador_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedInteger('periodo');        // YYYYMM
            $table->unsignedSmallInteger('periodo_anio');
            $table->unsignedTinyInteger('periodo_mes');
            $table->string('tipo_corrida', 30)->default('mensual'); // mensual, sac, final...
            $table->string('codigo', 30);              // acumulador (REM, NOREM, DESC...)
            $table->decimal('valor', 18, 2)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            // Un valor por acumulador dentro de una misma corrida/empleado.
            $table->unique(['liquidacion_id', 'empleado_id', 'codigo'], 'liqacum_liq_emp_cod_uq');
            $table->index(['empleado_id', 'periodo', 'codigo'], 'liqacum_emp_periodo_cod_ix');
            $table->index(['empleado_id', 'codigo', 'tipo_corrida'], 'liqacum_emp_cod_tipo_ix');
            $table->index(['empresa_id', 'periodo'], 'liqacum_empresa_periodo_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_acumulador_sueldos');
    }
};
