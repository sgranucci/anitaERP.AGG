<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gastronomia_cierre_jornada_proceso_snapshot')) {
            return;
        }

        Schema::create('gastronomia_cierre_jornada_proceso_snapshot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jornada_gastronomia_id')->unique('uq_cierre_jornada_proceso_snap_jornada');
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha_jornada');
            $table->json('payload');
            $table->decimal('porcentaje', 8, 4)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('congelado_en');
            $table->timestamps();

            $table->foreign('jornada_gastronomia_id', 'fk_cjp_snap_jornada')
                ->references('id')->on('jornada_gastronomia')->onDelete('cascade');
            $table->foreign('empresa_id', 'fk_cjp_snap_empresa')
                ->references('id')->on('empresa');
            $table->foreign('usuario_id', 'fk_cjp_snap_usuario')
                ->references('id')->on('usuario');
            $table->index(['empresa_id', 'fecha_jornada'], 'idx_cjp_snap_empresa_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastronomia_cierre_jornada_proceso_snapshot');
    }
};
