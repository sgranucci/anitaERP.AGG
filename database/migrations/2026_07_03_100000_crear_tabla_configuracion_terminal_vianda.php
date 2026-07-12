<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identificador_pc', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('ubicacion', 255)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_config_terminal_vianda_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('deposito_platos_id');
            $table->foreign('deposito_platos_id', 'fk_config_terminal_vianda_dep_platos')
                ->references('id')->on('depmae')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('deposito_insumos_id');
            $table->foreign('deposito_insumos_id', 'fk_config_terminal_vianda_dep_insumos')
                ->references('id')->on('depmae')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('salida_voucher_id');
            $table->foreign('salida_voucher_id', 'fk_config_terminal_vianda_salida')
                ->references('id')->on('salida')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('listaprecio_id')->nullable();
            $table->foreign('listaprecio_id', 'fk_config_terminal_vianda_listaprecio')
                ->references('id')->on('listaprecio')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_id');
            $table->foreign('tipotransaccion_id', 'fk_config_terminal_vianda_tipotransaccion')
                ->references('id')->on('tipotransaccion')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->char('estado', 1)->default('A');
            $table->timestamps();
            $table->unique('identificador_pc', 'uk_config_terminal_vianda_pc');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_terminal_vianda');
    }
};
