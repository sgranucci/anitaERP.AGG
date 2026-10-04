<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precargas de cash flow (posición bancaria). No generan asiento hasta convertirlas
 * en un ingreso/egreso. Sin SoftDeletes: la baja es física y queda en audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finanza_movimiento_precarga', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha');
            $table->string('tipo', 20);
            $table->string('rubro', 40);
            $table->string('detalle', 255);
            $table->unsignedBigInteger('cuentacaja_id')->nullable();
            $table->unsignedBigInteger('cuentacaja_desde_id')->nullable();
            $table->unsignedBigInteger('cuentacaja_hasta_id')->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('monto', 18, 2);
            $table->decimal('cotizacion', 18, 6)->default(1);
            $table->unsignedBigInteger('cuentacontable_contrapartida_id')->nullable();
            $table->string('estado', 20)->default('abierto');
            $table->unsignedBigInteger('caja_movimiento_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_fmp_empresa')
                ->references('id')->on('empresa')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('cuentacaja_id', 'fk_fmp_cc')
                ->references('id')->on('cuentacaja')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('cuentacaja_desde_id', 'fk_fmp_cc_desde')
                ->references('id')->on('cuentacaja')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('cuentacaja_hasta_id', 'fk_fmp_cc_hasta')
                ->references('id')->on('cuentacaja')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('moneda_id', 'fk_fmp_moneda')
                ->references('id')->on('moneda')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('cuentacontable_contrapartida_id', 'fk_fmp_ctacont')
                ->references('id')->on('cuentacontable')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('caja_movimiento_id', 'fk_fmp_cajamov')
                ->references('id')->on('caja_movimiento')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('usuario_id', 'fk_fmp_usuario')
                ->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();

            $table->index(['fecha', 'empresa_id'], 'idx_fmp_fecha_emp');
            $table->index(['estado', 'fecha'], 'idx_fmp_estado_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finanza_movimiento_precarga');
    }
};
