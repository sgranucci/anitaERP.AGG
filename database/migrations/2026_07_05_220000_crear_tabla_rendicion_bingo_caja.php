<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_bingo_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 50);
            $table->unsignedBigInteger('nro_oper_anita')->nullable();
            $table->string('fuente_nro_oper', 30)->nullable();
            $table->timestamp('anita_sincronizado_en')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id')->nullable();
            $table->unsignedBigInteger('turno_operativo_bingo_id')->nullable();
            $table->unsignedBigInteger('jornada_bingo_id')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->dateTime('fecharendicion');
            $table->date('fecha_jornada');
            $table->unsignedInteger('cant_cartones')->default(0);
            $table->decimal('total_cartones', 15, 2)->default(0);
            $table->decimal('saldo_final', 15, 2)->default(0);
            $table->decimal('sobrante_faltante', 15, 2)->default(0);
            $table->decimal('vales', 15, 2)->default(0);
            $table->decimal('redondeo', 15, 2)->default(0);
            $table->decimal('deposito', 15, 2)->default(0);
            $table->json('cartones_json')->nullable();
            $table->json('conceptos_json')->nullable();
            $table->json('medios_contado_json')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('cerro_turno')->default(false);
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_rendicion_bingo_caja_empresa')
                ->references('id')->on('empresa')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('cuentacaja_id', 'fk_rendicion_bingo_caja_cuentacaja')
                ->references('id')->on('cuentacaja')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('turno_operativo_bingo_id', 'fk_rendicion_bingo_caja_turno')
                ->references('id')->on('turno_operativo_bingo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('jornada_bingo_id', 'fk_rendicion_bingo_caja_jornada')
                ->references('id')->on('jornada_bingo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('creousuario_id', 'fk_rendicion_bingo_caja_usuario')
                ->references('id')->on('usuario')->restrictOnDelete()->restrictOnUpdate();

            $table->unique('turno_operativo_bingo_id', 'uq_rendicion_bingo_caja_turno');
            $table->index(['empresa_id', 'fecha_jornada'], 'idx_rendicion_bingo_caja_empresa_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_bingo_caja');
    }
};
