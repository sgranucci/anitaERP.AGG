<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identificador_pc', 100);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_config_pv_gastronomia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_cae_id');
            $table->foreign('puntoventa_cae_id', 'fk_config_pv_gastronomia_puntoventa_cae')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_caea_id');
            $table->foreign('puntoventa_caea_id', 'fk_config_pv_gastronomia_puntoventa_caea')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id', 'fk_config_pv_gastronomia_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('salida_comanda_id');
            $table->foreign('salida_comanda_id', 'fk_config_pv_gastronomia_salida_comanda')
                ->references('id')->on('salida')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('salida_factura_id');
            $table->foreign('salida_factura_id', 'fk_config_pv_gastronomia_salida_factura')
                ->references('id')->on('salida')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['identificador_pc', 'empresa_id'], 'uk_config_pv_gastronomia_pc_empresa');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_puntoventa_gastronomia');
    }
};
