<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajuste_inflacion_indice', function (Blueprint $table) {
            $table->id();
            $table->date('periodo');
            $table->decimal('valor', 22, 8);
            $table->string('fuente', 120)->default('FACPCE RT 6');
            $table->boolean('provisorio')->default(false);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->unique('periodo', 'uq_ai_indice_periodo');
            $table->foreign('usuario_id', 'fk_ai_indice_usuario')
                ->references('id')->on('usuario')
                ->nullOnDelete();
        });

        Schema::create('ajuste_inflacion_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_recpam_id');
            $table->unsignedBigInteger('centrocosto_recpam_id')->nullable();
            $table->unsignedBigInteger('tipoasiento_id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('empresa_id', 'uq_ai_config_empresa');
            $table->foreign('empresa_id', 'fk_ai_config_empresa')
                ->references('id')->on('empresa')
                ->cascadeOnDelete();
            $table->foreign('cuentacontable_recpam_id', 'fk_ai_config_cuenta')
                ->references('id')->on('cuentacontable')
                ->restrictOnDelete();
            $table->foreign('centrocosto_recpam_id', 'fk_ai_config_ccosto')
                ->references('id')->on('centrocosto')
                ->nullOnDelete();
            $table->foreign('tipoasiento_id', 'fk_ai_config_tipoasiento')
                ->references('id')->on('tipoasiento')
                ->restrictOnDelete();
        });

        Schema::create('ajuste_inflacion_cuenta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->boolean('activo')->default(true);
            $table->string('metodo_anticuacion', 32)->default('movimientos_mensuales');
            $table->timestamps();

            $table->unique(['empresa_id', 'cuentacontable_id'], 'uq_ai_cuenta_empresa_cuenta');
            $table->index(['empresa_id', 'activo'], 'idx_ai_cuenta_empresa_activo');
            $table->foreign('empresa_id', 'fk_ai_cuenta_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('cuentacontable_id', 'fk_ai_cuenta_cuenta')
                ->references('id')->on('cuentacontable')
                ->cascadeOnDelete();
        });

        Schema::create('ajuste_inflacion_corrida', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->date('periodo_desde');
            $table->date('fecha_cierre');
            $table->unsignedBigInteger('indice_cierre_id');
            $table->string('estado', 24)->default('simulada');
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->string('confirmada_clave', 80)->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('confirmado_por_id')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->string('observacion', 2000)->nullable();
            $table->decimal('total_ajuste', 22, 4)->default(0);
            $table->string('firma', 64);
            $table->timestamps();

            $table->unique('confirmada_clave', 'uq_ai_corrida_confirmada_clave');
            $table->index(['empresa_id', 'fecha_cierre'], 'idx_ai_corrida_empresa_cierre');
            $table->index(['empresa_id', 'estado'], 'idx_ai_corrida_empresa_estado');
            $table->index(['periodo_desde', 'fecha_cierre'], 'idx_ai_corrida_periodo');
            $table->index('asiento_id', 'idx_ai_corrida_asiento');
            $table->index('indice_cierre_id', 'idx_ai_corrida_indice');

            $table->foreign('empresa_id', 'fk_ai_corrida_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete();
            $table->foreign('indice_cierre_id', 'fk_ai_corrida_indice')
                ->references('id')->on('ajuste_inflacion_indice')
                ->restrictOnDelete();
            $table->foreign('asiento_id', 'fk_ai_corrida_asiento')
                ->references('id')->on('asiento')
                ->nullOnDelete();
            $table->foreign('usuario_id', 'fk_ai_corrida_usuario')
                ->references('id')->on('usuario')
                ->restrictOnDelete();
            $table->foreign('confirmado_por_id', 'fk_ai_corrida_confirmador')
                ->references('id')->on('usuario')
                ->nullOnDelete();
        });

        Schema::create('ajuste_inflacion_corrida_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corrida_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->date('periodo_origen');
            $table->unsignedBigInteger('indice_origen_id');
            $table->decimal('saldo_origen', 22, 4);
            $table->decimal('coeficiente', 22, 10);
            $table->decimal('importe_reexpresado', 22, 4);
            $table->decimal('ajuste', 22, 4);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->index(['corrida_id', 'cuentacontable_id'], 'idx_ai_det_corrida_cuenta');
            $table->index(['corrida_id', 'periodo_origen'], 'idx_ai_det_corrida_periodo');
            $table->index(['cuentacontable_id', 'periodo_origen'], 'idx_ai_det_cuenta_periodo');
            $table->index('indice_origen_id', 'idx_ai_det_indice');
            $table->index('centrocosto_id', 'idx_ai_det_ccosto');

            $table->foreign('corrida_id', 'fk_ai_det_corrida')
                ->references('id')->on('ajuste_inflacion_corrida')
                ->cascadeOnDelete();
            $table->foreign('cuentacontable_id', 'fk_ai_det_cuenta')
                ->references('id')->on('cuentacontable')
                ->restrictOnDelete();
            $table->foreign('centrocosto_id', 'fk_ai_det_ccosto')
                ->references('id')->on('centrocosto')
                ->nullOnDelete();
            $table->foreign('indice_origen_id', 'fk_ai_det_indice')
                ->references('id')->on('ajuste_inflacion_indice')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_inflacion_corrida_detalle');
        Schema::dropIfExists('ajuste_inflacion_corrida');
        Schema::dropIfExists('ajuste_inflacion_cuenta');
        Schema::dropIfExists('ajuste_inflacion_configuracion');
        Schema::dropIfExists('ajuste_inflacion_indice');
    }
};
