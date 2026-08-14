<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_perdida', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo');
            $table->string('nombre', 30);
            $table->timestamps();

            $table->unique('codigo', 'uq_concepto_perdida_codigo');
            $table->index('nombre', 'idx_concepto_perdida_nombre');
        });

        Schema::create('imputacion_perdida', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo');
            $table->string('nombre', 30);
            $table->timestamps();

            $table->unique('codigo', 'uq_imputacion_perdida_codigo');
            $table->index('nombre', 'idx_imputacion_perdida_nombre');
        });

        Schema::create('imputacion_perdida_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imputacion_perdida_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->timestamps();

            $table->unique(['imputacion_perdida_id', 'empresa_id'], 'uq_imputacion_perdida_empresa');
            $table->index(['empresa_id'], 'idx_imputacion_perdida_empresa_emp');

            $table->foreign('imputacion_perdida_id', 'fk_ipe_imputacion_perdida')
                ->references('id')->on('imputacion_perdida')
                ->cascadeOnDelete();
            $table->foreign('empresa_id', 'fk_ipe_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_id', 'fk_ipe_cuentacontable')
                ->references('id')->on('cuentacontable')
                ->restrictOnDelete();
        });

        Schema::create('perdida_personal', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->unsignedBigInteger('imputacion_perdida_id');
            $table->unsignedBigInteger('concepto_perdida_id');
            $table->unsignedBigInteger('empleado_sueldos_id');
            $table->unsignedBigInteger('supervisor_empleado_sueldos_id');
            $table->char('turno', 1);
            $table->date('fecha_ingreso')->nullable();
            $table->string('hora_ingreso', 8)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->char('estado', 1)->default('P');
            $table->string('leyenda', 80)->nullable();
            $table->string('maquina', 10)->nullable();
            $table->decimal('importe', 15, 2);
            $table->timestamps();

            $table->unique('numero', 'uq_perdida_personal_numero');
            $table->index(['fecha', 'numero'], 'idx_perdida_personal_fecha_numero');
            $table->index(['empresa_id', 'fecha'], 'idx_perdida_personal_empresa_fecha');
            $table->index(['empleado_sueldos_id', 'fecha'], 'idx_perdida_personal_empleado_fecha');

            $table->foreign('empresa_id', 'fk_pp_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('centrocosto_id', 'fk_pp_centrocosto')
                ->references('id')->on('centrocosto')
                ->nullOnDelete();
            $table->foreign('imputacion_perdida_id', 'fk_pp_imputacion')
                ->references('id')->on('imputacion_perdida')
                ->restrictOnDelete();
            $table->foreign('concepto_perdida_id', 'fk_pp_concepto')
                ->references('id')->on('concepto_perdida')
                ->restrictOnDelete();
            $table->foreign('empleado_sueldos_id', 'fk_pp_empleado')
                ->references('id')->on('empleado_sueldos')
                ->restrictOnDelete();
            $table->foreign('supervisor_empleado_sueldos_id', 'fk_pp_supervisor')
                ->references('id')->on('empleado_sueldos')
                ->restrictOnDelete();
            $table->foreign('usuario_id', 'fk_pp_usuario')
                ->references('id')->on('usuario')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perdida_personal');
        Schema::dropIfExists('imputacion_perdida_empresa');
        Schema::dropIfExists('imputacion_perdida');
        Schema::dropIfExists('concepto_perdida');
    }
};
