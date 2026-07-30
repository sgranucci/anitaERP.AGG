<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina')) {
            Schema::create('rendicion_maquina', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 30)->nullable()->unique();
                $table->unsignedInteger('nro_oper_anita')->nullable();
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->string('turno', 1);
                $table->string('estado', 20)->default('confirmada');
                $table->unsignedBigInteger('supervisor_usuario_id')->nullable();
                $table->unsignedBigInteger('auxiliar_usuario_id')->nullable();
                $table->unsignedBigInteger('cajero_usuario_id')->nullable();
                $table->unsignedBigInteger('creousuario_id');
                $table->string('observacion', 500)->nullable();
                $table->json('inputs_json')->nullable();
                $table->json('wigos_json')->nullable();
                $table->json('calc_json')->nullable();
                $table->decimal('total_ingreso', 18, 2)->default(0);
                $table->decimal('total_salida', 18, 2)->default(0);
                $table->decimal('resultado_turno', 18, 2)->default(0);
                $table->decimal('transferencia', 18, 2)->default(0);
                $table->decimal('fondo_cierre', 18, 2)->default(0);
                $table->decimal('fondo_inicial', 18, 2)->default(0);
                $table->decimal('dif_caja', 18, 2)->default(0);
                $table->timestamp('anita_sincronizado_en')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('empresa_id', 'fk_rendmaq_empresa')
                    ->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('supervisor_usuario_id', 'fk_rendmaq_supervisor')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('auxiliar_usuario_id', 'fk_rendmaq_auxiliar')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('cajero_usuario_id', 'fk_rendmaq_cajero')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('creousuario_id', 'fk_rendmaq_creousuario')
                    ->references('id')->on('usuario')->restrictOnDelete();

                $table->index(['empresa_id', 'fecha', 'turno'], 'idx_rendmaq_emp_fecha_turno');
                $table->index(['estado'], 'idx_rendmaq_estado');
            });
        }

        if (! Schema::hasTable('rendicion_maquina_valor')) {
            Schema::create('rendicion_maquina_valor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rendicion_maquina_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->unsignedInteger('codigo_valormae')->nullable();
                $table->decimal('monto', 18, 2)->default(0);
                $table->decimal('cotizacion', 18, 6)->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('rendicion_maquina_id', 'fk_rendmaq_val_rendicion')
                    ->references('id')->on('rendicion_maquina')->cascadeOnDelete();
                $table->foreign('cuentacaja_id', 'fk_rendmaq_val_cuentacaja')
                    ->references('id')->on('cuentacaja')->restrictOnDelete();

                $table->index(['rendicion_maquina_id', 'orden'], 'idx_rendmaq_val_orden');
            });
        }

        if (! Schema::hasTable('rendicion_maquina_gasto')) {
            Schema::create('rendicion_maquina_gasto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('rendicion_maquina_id');
                $table->unsignedBigInteger('apertura_gasto_id');
                $table->decimal('monto', 18, 2)->default(0);
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('rendicion_maquina_id', 'fk_rendmaq_gasto_rendicion')
                    ->references('id')->on('rendicion_maquina')->cascadeOnDelete();
                $table->foreign('apertura_gasto_id', 'fk_rendmaq_gasto_apgasto')
                    ->references('id')->on('apertura_gasto')->restrictOnDelete();

                $table->index(['rendicion_maquina_id', 'orden'], 'idx_rendmaq_gasto_orden');
            });
        }

        if (Schema::hasTable('rendicion_maquina_ajuste_wigos')
            && Schema::hasColumn('rendicion_maquina_ajuste_wigos', 'rendicion_maquina_id')
        ) {
            try {
                Schema::table('rendicion_maquina_ajuste_wigos', function (Blueprint $table) {
                    $table->foreign('rendicion_maquina_id', 'fk_rendmaq_ajw_rendicion')
                        ->references('id')->on('rendicion_maquina')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK ya existente o motor sin soporte en re-run
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rendicion_maquina_ajuste_wigos')) {
            try {
                Schema::table('rendicion_maquina_ajuste_wigos', function (Blueprint $table) {
                    $table->dropForeign('fk_rendmaq_ajw_rendicion');
                });
            } catch (\Throwable) {
                // noop
            }
        }

        Schema::dropIfExists('rendicion_maquina_gasto');
        Schema::dropIfExists('rendicion_maquina_valor');
        Schema::dropIfExists('rendicion_maquina');
    }
};
