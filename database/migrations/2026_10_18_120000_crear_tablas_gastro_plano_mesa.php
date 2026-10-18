<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planos de mesas de gastronomía (editor gráfico) y asignación a PC del POS.
 * Sin SoftDeletes; auditoría OwenIt en el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gastro_plano_mesa')) {
            Schema::create('gastro_plano_mesa', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('sala_id')->nullable();
                $table->string('codigo', 20)->nullable();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->unsignedInteger('orden')->default(0);
                $table->json('layout_json')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_gplano_empresa')
                    ->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('sala_id', 'fk_gplano_sala')
                    ->references('id')->on('sala')->nullOnDelete();
                $table->index(['empresa_id', 'sala_id'], 'idx_gplano_empresa_sala');
                $table->index(['activo', 'orden'], 'idx_gplano_activo_orden');
            });
        }

        if (! Schema::hasTable('configuracion_puntoventa_gastronomia_plano')) {
            Schema::create('configuracion_puntoventa_gastronomia_plano', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('configuracion_puntoventa_gastronomia_id');
                $table->unsignedBigInteger('gastro_plano_mesa_id');
                $table->unsignedInteger('orden')->default(0);

                $table->foreign('configuracion_puntoventa_gastronomia_id', 'fk_gcpv_plano_config')
                    ->references('id')->on('configuracion_puntoventa_gastronomia')->cascadeOnDelete();
                $table->foreign('gastro_plano_mesa_id', 'fk_gcpv_plano_plano')
                    ->references('id')->on('gastro_plano_mesa')->cascadeOnDelete();
                $table->unique(
                    ['configuracion_puntoventa_gastronomia_id', 'gastro_plano_mesa_id'],
                    'uk_gcpv_plano_config_plano'
                );
                $table->index(['configuracion_puntoventa_gastronomia_id', 'orden'], 'idx_gcpv_plano_orden');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_puntoventa_gastronomia_plano');
        Schema::dropIfExists('gastro_plano_mesa');
    }
};
