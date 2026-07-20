<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Maestro de liquidaciones = cabecera de la "corrida de liquidacion" (payroll run).
 *
 * Origen Anita: maeliq (mael_*). Se moderniza al estandar de sistemas elite:
 *  - Periodo devengado con desde/hasta (Anita solo tenia per_liq char(6)) para
 *    prorrateo, altas/bajas parciales y ausencias.
 *  - Maquina de estados clara (borrador -> calculada -> cerrada -> contabilizada
 *    -> pagada / anulada) en vez de un unico char + fecha_cierre.
 *  - Simulacion vs definitiva (correr en prueba sin impactar acumuladores).
 *  - Contabilizacion (asiento) y pago trazados en la cabecera.
 *  - Totales cacheados para listados rapidos.
 *
 * La cabecera se crea como PRIMER paso del proceso de liquidacion (alta en
 * borrador) y se administra desde el index de corridas; no es un ABM aislado.
 *
 * Mapeo Anita -> ERP:
 *  mael_empresa       -> empresa_id
 *  mael_liquidacion   -> numero
 *  mael_detalle       -> descripcion
 *  mael_tipo_liq      -> tipo
 *  mael_per_liq       -> periodo (YYYYMM) + periodo_anio/periodo_mes
 *  mael_fecha_liq     -> fecha_liquidacion
 *  mael_fecha_pago    -> fecha_pago
 *  mael_lugar_pago    -> lugar_pago
 *  mael_estado        -> estado
 *  mael_fecha_cierre  -> fecha_cierre
 *  mael_usu_cierre    -> usuario_cierre_id
 *  mael_acum_novedad  -> acumula_novedades
 *  mael_banco_dep     -> banco_deposito
 *  mael_per_dep       -> periodo_deposito
 *  mael_fe_ult_dep    -> fecha_ultimo_deposito
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('numero');                     // numero de corrida por empresa (Anita mael_liquidacion)
            $table->string('descripcion', 60);                     // Anita mael_detalle

            // Tipo de liquidacion (alineado con concepto_sueldos.momento)
            $table->string('tipo', 30)->default('mensual');
            // mensual | quincena_1 | quincena_2 | sac | vacaciones | final |
            // complementaria | ajuste | gratificacion | no_remunerativo | especial

            // Periodo devengado
            $table->char('periodo', 6);                            // YYYYMM (Anita mael_per_liq)
            $table->unsignedSmallInteger('periodo_anio');
            $table->unsignedTinyInteger('periodo_mes');
            $table->date('periodo_desde')->nullable();             // rango devengado (prorrateo)
            $table->date('periodo_hasta')->nullable();

            // Fechas del proceso
            $table->date('fecha_liquidacion')->nullable();         // Anita mael_fecha_liq
            $table->date('fecha_pago')->nullable();                // Anita mael_fecha_pago
            $table->string('lugar_pago', 60)->nullable();          // Anita mael_lugar_pago

            // Estado / workflow
            $table->string('estado', 20)->default('borrador');
            // borrador | calculada | revisada | cerrada | contabilizada | pagada | anulada
            $table->boolean('simulacion')->default(false);         // definitiva vs prueba
            $table->boolean('acumula_novedades')->default(true);   // Anita mael_acum_novedad

            // Alcance (poblacion liquidada, para reproducibilidad del calculo)
            $table->string('alcance', 20)->default('todos');       // todos | agrupamiento | sindicato | seleccion
            $table->text('filtros_json')->nullable();

            // Deposito bancario
            $table->string('banco_deposito', 60)->nullable();      // Anita mael_banco_dep
            $table->string('periodo_deposito', 15)->nullable();    // Anita mael_per_dep
            $table->date('fecha_ultimo_deposito')->nullable();     // Anita mael_fe_ult_dep

            // Totales cacheados (se recalculan al liquidar; aceleran listados)
            $table->unsignedInteger('cantidad_recibos')->default(0);
            $table->decimal('total_bruto', 18, 2)->default(0);
            $table->decimal('total_remunerativo', 18, 2)->default(0);
            $table->decimal('total_no_remunerativo', 18, 2)->default(0);
            $table->decimal('total_descuentos', 18, 2)->default(0);
            $table->decimal('total_neto', 18, 2)->default(0);

            // Contabilizacion
            $table->boolean('contabilizado')->default(false);
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->dateTime('fecha_contabilizacion')->nullable();

            // Auditoria del proceso
            $table->dateTime('fecha_calculo')->nullable();         // ultimo calculo
            $table->dateTime('fecha_cierre')->nullable();          // Anita mael_fecha_cierre
            $table->unsignedBigInteger('usuario_id')->nullable();  // creador
            $table->unsignedBigInteger('usuario_cierre_id')->nullable(); // Anita mael_usu_cierre

            $table->text('observacion')->nullable();

            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['empresa_id', 'numero'], 'liquidacion_empresa_numero_uq');
            $table->index(['empresa_id', 'periodo']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['tipo', 'periodo']);
            $table->index('fecha_pago');

            $table->foreign('empresa_id')->references('id')->on('empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_sueldos');
    }
};
