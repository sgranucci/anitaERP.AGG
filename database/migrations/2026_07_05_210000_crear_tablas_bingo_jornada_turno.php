<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornada_bingo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha_jornada');
            $table->string('estado', 20)->default('abierta');
            $table->unsignedBigInteger('usuario_apertura_id')->nullable();
            $table->unsignedBigInteger('usuario_cierre_id')->nullable();
            $table->timestamp('apertura_en')->nullable();
            $table->timestamp('cierre_en')->nullable();
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_jornada_bingo_empresa')->references('id')->on('empresa');
            $table->foreign('usuario_apertura_id', 'fk_jornada_bingo_usuario_apertura')->references('id')->on('usuario');
            $table->foreign('usuario_cierre_id', 'fk_jornada_bingo_usuario_cierre')->references('id')->on('usuario');
            $table->index(['empresa_id', 'estado'], 'idx_jornada_bingo_empresa_estado');
            $table->index(['empresa_id', 'fecha_jornada'], 'idx_jornada_bingo_empresa_fecha');
        });

        Schema::create('turno_bingo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre', 255);
            $table->string('codigo', 50)->nullable();
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_turno_bingo_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->unique(['nombre', 'empresa_id'], 'uk_turno_bingo_nombre_empresa');
            $table->unique(['codigo', 'empresa_id'], 'uk_turno_bingo_codigo_empresa');
        });

        Schema::create('configuracion_puntoventa_bingo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identificador_pc', 100);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_cfg_pv_bingo_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->foreign('cuentacaja_id', 'fk_cfg_pv_bingo_cuentacaja')
                ->references('id')->on('cuentacaja')
                ->nullOnDelete()
                ->restrictOnUpdate();
            $table->unique(['identificador_pc', 'empresa_id'], 'uk_cfg_pv_bingo_pc_empresa');
        });

        Schema::create('turno_operativo_bingo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('jornada_bingo_id');
            $table->unsignedBigInteger('turno_bingo_id');
            $table->unsignedBigInteger('configuracion_puntoventa_bingo_id');
            $table->string('identificador_pc', 100);
            $table->string('estado', 20)->default('habilitado');
            $table->unsignedBigInteger('usuario_habilitacion_id');
            $table->unsignedBigInteger('usuario_habilitado_id');
            $table->decimal('monto_habilitacion', 15, 2)->default(0);
            $table->text('observacion_habilitacion')->nullable();
            $table->dateTime('habilitacion_en');
            $table->unsignedBigInteger('usuario_cierre_id')->nullable();
            $table->dateTime('cierre_en')->nullable();
            $table->unsignedInteger('numero_cierre')->nullable();
            $table->decimal('monto_rendicion_turno', 15, 2)->nullable();
            $table->decimal('monto_rendicion_dia', 15, 2)->nullable();
            $table->decimal('redondeo', 15, 2)->nullable();
            $table->decimal('sobrante_faltante', 15, 2)->nullable();
            $table->decimal('vales', 15, 2)->nullable();
            $table->decimal('deposito', 15, 2)->nullable();
            $table->json('medios_contado_cierre_json')->nullable();
            $table->json('cartones_rendicion_json')->nullable();
            $table->json('conceptos_rendicion_json')->nullable();
            $table->boolean('rendicion_presentada')->default(false);
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_turno_operativo_bingo_empresa')
                ->references('id')->on('empresa')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('jornada_bingo_id', 'fk_turno_operativo_bingo_jornada')
                ->references('id')->on('jornada_bingo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('turno_bingo_id', 'fk_turno_operativo_bingo_turno')
                ->references('id')->on('turno_bingo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('configuracion_puntoventa_bingo_id', 'fk_turno_operativo_bingo_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_bingo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('usuario_habilitacion_id', 'fk_turno_operativo_bingo_usuario_hab')
                ->references('id')->on('usuario')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('usuario_habilitado_id', 'fk_turno_operativo_bingo_usuario_hab_a')
                ->references('id')->on('usuario')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('usuario_cierre_id', 'fk_turno_operativo_bingo_usuario_cierre')
                ->references('id')->on('usuario')->nullOnDelete()->restrictOnUpdate();
            $table->index(['identificador_pc', 'estado'], 'idx_turno_operativo_bingo_pc_estado');
            $table->index(['empresa_id', 'estado'], 'idx_turno_operativo_bingo_empresa_estado');
        });

        Schema::create('cierre_parcial_turno_bingo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('turno_operativo_bingo_id');
            $table->unsignedSmallInteger('numero_parcial');
            $table->string('identificador_pc', 100);
            $table->decimal('total_rendicion_turno', 15, 2)->default(0);
            $table->json('totales_json');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('turno_operativo_bingo_id', 'fk_cierre_parcial_turno_operativo_bingo')
                ->references('id')->on('turno_operativo_bingo')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('usuario_id', 'fk_cierre_parcial_turno_bingo_usuario')
                ->references('id')->on('usuario')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(
                ['turno_operativo_bingo_id', 'numero_parcial'],
                'uk_cierre_parcial_turno_bingo_numero'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_parcial_turno_bingo');
        Schema::dropIfExists('turno_operativo_bingo');
        Schema::dropIfExists('configuracion_puntoventa_bingo');
        Schema::dropIfExists('turno_bingo');
        Schema::dropIfExists('jornada_bingo');
    }
};
