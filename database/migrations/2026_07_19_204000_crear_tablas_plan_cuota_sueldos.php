<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Plan de cuotas: un concepto que se liquida N veces y "cae" automáticamente
 * al completarse (préstamos, anticipos, embargos en cuotas).
 *
 * - Cabecera (plan) con contador de cuotas, saldo y estado.
 * - Ledger de movimientos por corrida (idempotente por liquidación): pendiente
 *   al calcular, confirmado al cerrar la corrida; revertible al reabrir/anular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_plan_cuota_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('concepto_id'); // concepto con el que se liquida
            $table->string('descripcion', 120);

            // Valor de la cuota: fijo o por fórmula ERP evaluada cada mes.
            $table->string('tipo_valor', 20)->default('fijo'); // fijo | formula
            $table->decimal('cuota_valor', 18, 2)->nullable();
            $table->text('cuota_formula')->nullable();

            $table->decimal('importe_total', 18, 2)->nullable(); // informativo / saldo
            $table->unsignedInteger('cuotas_totales');
            $table->unsignedInteger('cuotas_liquidadas')->default(0);
            $table->unsignedInteger('periodo_inicio'); // YYYYMM primera cuota

            // Tipos de corrida en los que descuenta (['mensual'] por defecto).
            $table->json('corridas_afecta')->nullable();

            $table->string('estado', 20)->default('activa'); // activa|suspendida|finalizada|cancelada
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'estado'], 'plan_cuota_emp_estado_ix');
            $table->index('concepto_id', 'plan_cuota_concepto_ix');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos');
        });

        Schema::create('empleado_plan_cuota_movimiento_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedInteger('periodo'); // YYYYMM
            $table->unsignedInteger('numero_cuota');
            $table->decimal('importe', 18, 2)->default(0);
            $table->date('fecha')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente|confirmado
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['plan_id', 'liquidacion_id'], 'plan_cuota_mov_plan_liq_uq');
            $table->index(['liquidacion_id', 'estado'], 'plan_cuota_mov_liq_estado_ix');
            $table->foreign('plan_id')->references('id')->on('empleado_plan_cuota_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_plan_cuota_movimiento_sueldos');
        Schema::dropIfExists('empleado_plan_cuota_sueldos');
    }
};
