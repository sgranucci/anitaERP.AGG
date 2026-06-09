<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_puntoventa_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identificador_pc', 100);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cfg_pv_estacionamiento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_cae_id');
            $table->foreign('puntoventa_cae_id', 'fk_cfg_pv_estacionamiento_puntoventa_cae')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_caea_id');
            $table->foreign('puntoventa_caea_id', 'fk_cfg_pv_estacionamiento_puntoventa_caea')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('lista_precio_estacionamiento_id');
            $table->foreign('lista_precio_estacionamiento_id', 'fk_cfg_pv_estacionamiento_lista_precio')
                ->references('id')->on('lista_precio_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('salida_factura_id');
            $table->foreign('salida_factura_id', 'fk_cfg_pv_estacionamiento_salida_factura')
                ->references('id')->on('salida')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_id')->nullable();
            $table->foreign('tipotransaccion_id', 'fk_cfg_pv_estacionamiento_tipotransaccion')
                ->references('id')->on('tipotransaccion')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_nota_credito_id')->nullable();
            $table->foreign('tipotransaccion_nota_credito_id', 'fk_cfg_pv_estacionamiento_tt_nc')
                ->references('id')->on('tipotransaccion')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_caja_id')->nullable();
            $table->foreign('tipotransaccion_caja_id', 'fk_cfg_pv_estacionamiento_tt_caja')
                ->references('id')->on('tipotransaccion_caja')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['identificador_pc', 'empresa_id'], 'uk_cfg_pv_estacionamiento_pc_empresa');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_puntoventa_estacionamiento');
    }
};
