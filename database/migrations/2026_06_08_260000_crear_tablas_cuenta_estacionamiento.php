<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuenta_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cuenta_estacionamiento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('identificador_pc', 100);
            $table->string('estado', 20)->default('abierta');
            $table->unsignedBigInteger('categoria_automovil_estacionamiento_id')->nullable();
            $table->foreign('categoria_automovil_estacionamiento_id', 'fk_cuenta_estacionamiento_categoria')
                ->references('id')->on('categoria_automovil_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('patente', 20)->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id', 'fk_cuenta_estacionamiento_cliente')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('descuento_estacionamiento_id')->nullable();
            $table->foreign('descuento_estacionamiento_id', 'fk_cuenta_estacionamiento_desc')
                ->references('id')->on('descuento_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('cliente_interno_descuento_id')->nullable();
            $table->foreign('cliente_interno_descuento_id', 'fk_cuenta_estacionamiento_cli_int_desc')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('factura_receptor_nombre', 255)->nullable();
            $table->string('factura_receptor_documento', 32)->nullable();
            $table->string('factura_receptor_domicilio', 255)->nullable();
            $table->unsignedBigInteger('factura_receptor_tipodocumento_id')->nullable();
            $table->foreign('factura_receptor_tipodocumento_id', 'fk_cuenta_estacionamiento_tipodoc')
                ->references('id')->on('tipodocumento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('configuracion_puntoventa_estacionamiento_id');
            $table->foreign('configuracion_puntoventa_estacionamiento_id', 'fk_cuenta_estacionamiento_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_estacionamiento_id')->nullable();
            $table->foreign('jornada_estacionamiento_id', 'fk_cuenta_estacionamiento_jornada')
                ->references('id')->on('jornada_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('turno_operativo_estacionamiento_id')->nullable();
            $table->foreign('turno_operativo_estacionamiento_id', 'fk_cuenta_estacionamiento_turno')
                ->references('id')->on('turno_operativo_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_cuenta_estacionamiento_venta')
                ->references('id')->on('venta')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('ticket_estacionamiento_id')->nullable();
            $table->foreign('ticket_estacionamiento_id', 'fk_cuenta_estacionamiento_ticket')
                ->references('id')->on('ticket_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['identificador_pc', 'estado'], 'idx_cuenta_estacionamiento_pc_estado');
        });

        Schema::create('cuenta_estacionamiento_linea', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cuenta_estacionamiento_id');
            $table->foreign('cuenta_estacionamiento_id', 'fk_linea_cuenta_estacionamiento')
                ->references('id')->on('cuenta_estacionamiento')
                ->onDelete('cascade')
                ->onUpdate('restrict');
            $table->unsignedSmallInteger('numero_linea')->default(1);
            $table->unsignedBigInteger('item_estacionamiento_id');
            $table->foreign('item_estacionamiento_id', 'fk_linea_estacionamiento_item')
                ->references('id')->on('item_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_linea_estacionamiento_art')
                ->references('id')->on('articulo')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->decimal('cantidad', 14, 4)->default(1);
            $table->decimal('precio_unitario', 14, 4);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('lista_precio_estacionamiento_item_id')->nullable();
            $table->foreign('lista_precio_estacionamiento_item_id', 'fk_linea_estacionamiento_lpi')
                ->references('id')->on('lista_precio_estacionamiento_item')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['cuenta_estacionamiento_id', 'numero_linea'], 'idx_linea_cuenta_est_num');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_estacionamiento_linea');
        Schema::dropIfExists('cuenta_estacionamiento');
    }
};
