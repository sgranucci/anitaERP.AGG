<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_operativo_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_turno_operativo_gastronomia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_gastronomia_id');
            $table->foreign('jornada_gastronomia_id', 'fk_turno_operativo_gastronomia_jornada')
                ->references('id')->on('jornada_gastronomia')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('turno_gastronomia_id');
            $table->foreign('turno_gastronomia_id', 'fk_turno_operativo_gastronomia_turno')
                ->references('id')->on('turno_gastronomia')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('configuracion_puntoventa_gastronomia_id');
            $table->foreign('configuracion_puntoventa_gastronomia_id', 'fk_turno_operativo_gastronomia_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_gastronomia')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('identificador_pc', 100);
            $table->string('estado', 20)->default('habilitado');
            $table->unsignedBigInteger('usuario_habilitacion_id');
            $table->foreign('usuario_habilitacion_id', 'fk_turno_operativo_gastronomia_usuario_hab')
                ->references('id')->on('usuario')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('usuario_habilitado_id');
            $table->foreign('usuario_habilitado_id', 'fk_turno_operativo_gastronomia_usuario_hab_a')
                ->references('id')->on('usuario')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->decimal('monto_habilitacion', 15, 2)->default(0);
            $table->text('observacion_habilitacion')->nullable();
            $table->dateTime('habilitacion_en');
            $table->unsignedBigInteger('usuario_cierre_id')->nullable();
            $table->foreign('usuario_cierre_id', 'fk_turno_operativo_gastronomia_usuario_cierre')
                ->references('id')->on('usuario')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->dateTime('cierre_en')->nullable();
            $table->decimal('monto_facturacion_turno', 15, 2)->nullable();
            $table->decimal('monto_facturacion_dia', 15, 2)->nullable();
            $table->decimal('redondeo_invitaciones', 15, 2)->nullable();
            $table->decimal('redondeo_turno', 15, 2)->nullable();
            $table->decimal('sobrante_faltante', 15, 2)->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();
            $table->index(['identificador_pc', 'estado'], 'idx_turno_operativo_pc_estado');
            $table->index(['empresa_id', 'estado'], 'idx_turno_operativo_empresa_estado');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('cierre_parcial_turno_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('turno_operativo_gastronomia_id');
            $table->foreign('turno_operativo_gastronomia_id', 'fk_cierre_parcial_turno_operativo')
                ->references('id')->on('turno_operativo_gastronomia')
                ->onDelete('cascade')
                ->onUpdate('restrict');
            $table->unsignedSmallInteger('numero_parcial');
            $table->string('identificador_pc', 100);
            $table->decimal('total_facturacion_turno', 15, 2)->default(0);
            $table->json('totales_json');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_cierre_parcial_turno_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['turno_operativo_gastronomia_id', 'numero_parcial'],
                'uk_cierre_parcial_turno_numero'
            );
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_parcial_turno_gastronomia');
        Schema::dropIfExists('turno_operativo_gastronomia');
    }
};
