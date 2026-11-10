<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estación KDS Crown por IP/host (distinta del POS).
 * La PC de cocina se fija a un área (ALIMENTOS, BEBIDAS…) y solo ve esas comandas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (Schema::hasTable('gastro_crown_kds_estacion')) {
            return;
        }

        Schema::create('gastro_crown_kds_estacion', function (Blueprint $table) {
            $table->id();
            $table->string('identificador_pc', 100);
            $table->string('descripcion', 120)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('sala_id')->nullable();
            $table->unsignedBigInteger('area_comanda_gastronomia_id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('identificador_pc', 'uq_gc_kds_estacion_pc');
            $table->foreign('area_comanda_gastronomia_id', 'fk_gc_kds_estacion_area')
                ->references('id')->on('area_comanda_gastronomia')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        Schema::dropIfExists('gastro_crown_kds_estacion');
    }
};
