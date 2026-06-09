<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_ticket_estacionamiento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('jornada_estacionamiento_id');
            $table->foreign('jornada_estacionamiento_id', 'fk_ticket_estacionamiento_jornada')
                ->references('id')->on('jornada_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('turno_operativo_estacionamiento_id')->nullable();
            $table->foreign('turno_operativo_estacionamiento_id', 'fk_ticket_estacionamiento_turno_operativo')
                ->references('id')->on('turno_operativo_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('configuracion_puntoventa_estacionamiento_id');
            $table->foreign('configuracion_puntoventa_estacionamiento_id', 'fk_ticket_estacionamiento_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_estacionamiento')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('identificador_pc', 100);
            $table->unsignedInteger('numero_ticket');
            $table->string('patente', 20);
            $table->unsignedBigInteger('categoria_automovil_estacionamiento_id')->nullable();
            $table->foreign('categoria_automovil_estacionamiento_id', 'fk_ticket_estacionamiento_categoria')
                ->references('id')->on('categoria_automovil_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('item_estacionamiento_id')->nullable();
            $table->foreign('item_estacionamiento_id', 'fk_ticket_estacionamiento_item')
                ->references('id')->on('item_estacionamiento')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('estado', 20)->default('ingreso');
            $table->dateTime('ingreso_en');
            $table->dateTime('salida_en')->nullable();
            $table->dateTime('facturado_en')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_ticket_estacionamiento_venta')
                ->references('id')->on('venta')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->decimal('monto_estimado', 15, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(
                ['empresa_id', 'jornada_estacionamiento_id', 'numero_ticket'],
                'uk_ticket_estacionamiento_empresa_jornada_numero'
            );
            $table->index(['identificador_pc', 'estado'], 'idx_ticket_estacionamiento_pc_estado');
            $table->index(['patente', 'estado'], 'idx_ticket_estacionamiento_patente_estado');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_estacionamiento');
    }
};
