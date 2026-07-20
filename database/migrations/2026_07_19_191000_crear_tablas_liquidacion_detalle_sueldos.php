<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Detalle de la corrida de liquidacion:
 *  - liquidacion_recibo_sueldos: cabecera por empleado (un recibo por corrida).
 *  - liquidacion_detalle_sueldos: renglones por concepto de cada recibo.
 *
 * Ambas guardan un "snapshot" de los datos del empleado y del concepto al
 * momento de liquidar, para que el recibo sea reproducible aunque cambien los
 * maestros (practica estandar en payroll: el recibo es un documento historico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_recibo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedInteger('legajo')->nullable();          // snapshot legajo (Anita)
            $table->unsignedInteger('numero_recibo')->nullable();   // correlativo del recibo

            // Snapshot del empleado (impresion/reproducibilidad)
            $table->string('apellido_nombre', 120)->nullable();
            $table->string('cuil', 15)->nullable();
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->string('categoria_desc', 60)->nullable();
            $table->unsignedBigInteger('agrupamiento_id')->nullable();
            $table->unsignedBigInteger('lugartrabajo_id')->nullable();
            $table->unsignedBigInteger('obrasocial_id')->nullable();
            $table->unsignedBigInteger('sindicato_id')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->decimal('sueldo_basico', 18, 2)->nullable();

            // Datos del periodo trabajado
            $table->decimal('dias_trabajados', 8, 2)->nullable();
            $table->decimal('dias_vacaciones', 8, 2)->nullable();
            $table->decimal('horas', 10, 2)->nullable();

            // Totales del recibo
            $table->decimal('total_remunerativo', 18, 2)->default(0);
            $table->decimal('total_no_remunerativo', 18, 2)->default(0);
            $table->decimal('total_bruto', 18, 2)->default(0);
            $table->decimal('total_descuentos', 18, 2)->default(0);
            $table->decimal('total_aportes', 18, 2)->default(0);
            $table->decimal('total_contribuciones', 18, 2)->default(0); // informativo
            $table->decimal('total_asignaciones', 18, 2)->default(0);   // no remunerativas / familiares
            $table->decimal('neto', 18, 2)->default(0);
            $table->decimal('redondeo', 18, 2)->default(0);
            $table->decimal('neto_a_pagar', 18, 2)->default(0);

            $table->string('estado', 20)->default('calculado');    // calculado | revisado | anulado
            $table->text('observacion')->nullable();

            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['liquidacion_id', 'empleado_id'], 'liqrecibo_liq_emp_uq');
            $table->index(['empleado_id']);
            $table->index(['liquidacion_id', 'legajo']);

            $table->foreign('liquidacion_id')->references('id')->on('liquidacion_sueldos')->onDelete('cascade');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos');
        });

        Schema::create('liquidacion_detalle_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recibo_id');
            // Denormalizado para reportes/acumuladores sin join (sin FK para evitar
            // multiples caminos de cascade; se limpia via cascade del recibo).
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('empleado_id');

            $table->unsignedBigInteger('concepto_id')->nullable();
            $table->unsignedInteger('concepto_codigo')->nullable();  // snapshot
            $table->string('concepto_descripcion', 60)->nullable();  // snapshot
            $table->string('tipo', 30)->nullable();                  // snapshot (remunerativo | descuento | ...)

            $table->unsignedInteger('nro_linea')->default(0);        // orden en el recibo
            $table->string('columna', 15)->default('haber');         // haber | descuento | neto | informativo

            $table->decimal('cantidad', 18, 4)->nullable();          // dias / horas / % / unidades
            $table->decimal('valor', 18, 4)->nullable();             // valor unitario / base aplicada
            $table->decimal('base_calculo', 18, 2)->nullable();      // base sobre la que se calculo (auditoria)
            $table->decimal('importe', 18, 2)->default(0);           // resultado (siempre positivo; el signo lo da columna)

            $table->boolean('remunerativo')->default(false);         // suma a base remunerativa
            $table->boolean('va_recibo')->default(true);
            $table->string('concepto_afip', 6)->nullable();          // snapshot para LSD/SICOSS
            $table->string('leyenda', 120)->nullable();

            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['recibo_id', 'nro_linea']);
            $table->index(['liquidacion_id', 'concepto_id']);
            $table->index(['empleado_id', 'concepto_id']);

            $table->foreign('recibo_id')->references('id')->on('liquidacion_recibo_sueldos')->onDelete('cascade');
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_detalle_sueldos');
        Schema::dropIfExists('liquidacion_recibo_sueldos');
    }
};
