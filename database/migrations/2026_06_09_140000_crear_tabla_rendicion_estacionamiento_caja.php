<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_estacionamiento_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipo', 20)->default('turno');
            $table->string('codigo', 50);
            $table->unsignedBigInteger('nro_oper_anita')->nullable();
            $table->string('fuente_nro_oper', 20)->nullable();
            $table->timestamp('anita_sincronizado_en')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_rendicion_estacionamiento_caja_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_cae_id');
            $table->foreign('puntoventa_cae_id', 'fk_rendicion_estacionamiento_caja_puntoventa_cae')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_caea_id');
            $table->foreign('puntoventa_caea_id', 'fk_rendicion_estacionamiento_caja_puntoventa_caea')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('caja_id');
            $table->foreign('caja_id', 'fk_rendicion_estacionamiento_caja_caja')->references('id')->on('caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_rendicion_estacionamiento_caja_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fecharendicion');
            $table->decimal('iniciodelfondo', 22, 2);
            $table->decimal('totalfactura', 22, 2);
            $table->decimal('totalcobrado', 22, 2);
            $table->decimal('totalinvitacion', 22, 2);
            $table->decimal('totalnotacredito', 22, 2);
            $table->decimal('totalredondeo', 22, 2);
            $table->decimal('totalredondeoinvitacion', 22, 2);
            $table->decimal('sobrantefaltante', 22, 2);
            $table->unsignedBigInteger('turno_operativo_estacionamiento_id')->nullable();
            $table->foreign('turno_operativo_estacionamiento_id', 'fk_rendicion_estacionamiento_caja_turno')->references('id')->on('turno_operativo_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_estacionamiento_id')->nullable();
            $table->foreign('jornada_estacionamiento_id', 'fk_rendicion_estacionamiento_caja_jornada')->references('id')->on('jornada_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
            $table->json('numeracion_comprobantes_json')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique('jornada_estacionamiento_id', 'uq_rendicion_estacionamiento_jornada');
            $table->index(['empresa_id', 'tipo'], 'idx_rendicion_estacionamiento_empresa_tipo');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_estacionamiento_caja');
    }
};
