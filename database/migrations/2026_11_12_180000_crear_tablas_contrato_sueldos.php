<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emisión de contratos / certificados laborales y de baja (migración desde Anita empcontrato + l-empcontr).
 *
 * - contrato_plantilla_sueldos: fuentes Word (.docx) cargados por el usuario
 * - contrato_plantilla_asignacion_sueldos: reglas tipo + modalidad + sindicato + empresa → plantilla
 * - empleado_contrato_sueldos: períodos/contratos del legajo (Anita empcontrato)
 * - empleado_contrato_emision_sueldos: historial de documentos emitidos
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contrato_plantilla_sueldos')) {
            Schema::create('contrato_plantilla_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 40);
                $table->string('nombre', 120);
                // CONTRATO | CERT_LABORAL | CERT_BAJA
                $table->string('tipo', 20);
                $table->string('archivo_path', 255);
                $table->string('archivo_nombre', 255)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('activo')->default(true);
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->unique('codigo', 'ctr_plant_codigo_uk');
                $table->index(['tipo', 'activo'], 'ctr_plant_tipo_act_ix');
            });
        }

        if (! Schema::hasTable('contrato_plantilla_asignacion_sueldos')) {
            Schema::create('contrato_plantilla_asignacion_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tipo', 20);
                // null = cualquier modalidad / sindicato / empresa
                $table->string('modalidad', 10)->nullable();
                $table->unsignedBigInteger('sindicato_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('contrato_plantilla_id');
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['tipo', 'activo', 'prioridad'], 'ctr_asig_tipo_act_prio_ix');
                $table->index(['modalidad', 'sindicato_id', 'empresa_id'], 'ctr_asig_criterio_ix');
                $table->foreign('contrato_plantilla_id', 'ctr_asig_plantilla_fk')
                    ->references('id')->on('contrato_plantilla_sueldos')
                    ->onDelete('cascade');
                $table->foreign('sindicato_id', 'ctr_asig_sindicato_fk')
                    ->references('id')->on('sindicato_sueldos')
                    ->onDelete('restrict');
                $table->foreign('empresa_id', 'ctr_asig_empresa_fk')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('empleado_contrato_sueldos')) {
            Schema::create('empleado_contrato_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedInteger('nro_orden')->default(1);
                $table->date('desde_fecha')->nullable();
                $table->date('hasta_fecha')->nullable();
                $table->string('comentario', 160)->nullable();
                $table->string('modalidad', 10)->nullable();
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->unsignedInteger('liquidacion')->nullable();
                // REEMPLAZO | EVENTO
                $table->string('motivo', 20)->nullable();
                $table->unsignedInteger('dias')->nullable();
                $table->unsignedBigInteger('empleado_reemplazo_id')->nullable();
                $table->timestamps();

                $table->unique(['empleado_id', 'nro_orden'], 'emp_ctr_emp_orden_uk');
                $table->index(['empleado_id', 'desde_fecha'], 'emp_ctr_emp_desde_ix');
                $table->foreign('empleado_id', 'emp_ctr_empleado_fk')
                    ->references('id')->on('empleado_sueldos')
                    ->onDelete('cascade');
                $table->foreign('empleado_reemplazo_id', 'emp_ctr_reemp_fk')
                    ->references('id')->on('empleado_sueldos')
                    ->onDelete('restrict');
                $table->foreign('centrocosto_id', 'emp_ctr_cc_fk')
                    ->references('id')->on('centrocosto')
                    ->onDelete('restrict');
                $table->foreign('categoria_id', 'emp_ctr_cat_fk')
                    ->references('id')->on('categoria_sueldos')
                    ->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('empleado_contrato_emision_sueldos')) {
            Schema::create('empleado_contrato_emision_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('empleado_contrato_id')->nullable();
                $table->string('tipo', 20);
                $table->unsignedBigInteger('contrato_plantilla_id')->nullable();
                $table->date('fecha_emision');
                $table->unsignedInteger('liquidacion')->nullable();
                $table->string('archivo_path', 255);
                $table->string('archivo_nombre', 255);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->json('datos_merge')->nullable();
                $table->timestamps();

                $table->index(['empleado_id', 'tipo', 'fecha_emision'], 'emp_ctr_em_emp_tipo_fec_ix');
                $table->foreign('empleado_id', 'emp_ctr_em_empleado_fk')
                    ->references('id')->on('empleado_sueldos')
                    ->onDelete('cascade');
                $table->foreign('empleado_contrato_id', 'emp_ctr_em_contrato_fk')
                    ->references('id')->on('empleado_contrato_sueldos')
                    ->onDelete('set null');
                $table->foreign('contrato_plantilla_id', 'emp_ctr_em_plantilla_fk')
                    ->references('id')->on('contrato_plantilla_sueldos')
                    ->onDelete('set null');
                $table->foreign('usuario_id', 'emp_ctr_em_usuario_fk')
                    ->references('id')->on('usuario')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_contrato_emision_sueldos');
        Schema::dropIfExists('empleado_contrato_sueldos');
        Schema::dropIfExists('contrato_plantilla_asignacion_sueldos');
        Schema::dropIfExists('contrato_plantilla_sueldos');
    }
};
