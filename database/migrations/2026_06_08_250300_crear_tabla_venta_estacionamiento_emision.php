<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_estacionamiento_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->primary();
            $table->foreign('venta_id', 'fk_vee_venta')
                ->references('id')->on('venta')
                ->onDelete('cascade')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('ticket_estacionamiento_id')->nullable();
            $table->foreign('ticket_estacionamiento_id', 'fk_vee_ticket')
                ->references('id')->on('ticket_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('identificador_pc', 100)->index();
            $table->unsignedBigInteger('configuracion_puntoventa_estacionamiento_id')->nullable();
            $table->foreign('configuracion_puntoventa_estacionamiento_id', 'fk_vee_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_estacionamiento_id')->nullable();
            $table->foreign('jornada_estacionamiento_id', 'fk_vee_jornada')
                ->references('id')->on('jornada_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('turno_operativo_estacionamiento_id')->nullable();
            $table->foreign('turno_operativo_estacionamiento_id', 'fk_vee_turno_operativo')
                ->references('id')->on('turno_operativo_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('venta_factura_origen_id')->nullable();
            $table->foreign('venta_factura_origen_id', 'fk_vee_venta_factura_origen')
                ->references('id')->on('venta')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_estacionamiento_emision');
    }
};
