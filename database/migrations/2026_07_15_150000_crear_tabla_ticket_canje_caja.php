<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emisión de vales/canjes gastronomía en anitaERP (reemplazo gradual de Informix tickettarj).
 * Serie movimiento_id + numero_ticket por empresa (código de barras 6+6 compatible con POS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_canje_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('movimiento_id');
            $table->unsignedInteger('numero_ticket');
            $table->date('fecha');
            $table->string('nro_documento', 20);
            $table->string('nombre_cliente', 120)->nullable();
            $table->boolean('es_vip')->default(false);
            $table->unsignedBigInteger('cliente_vip_caja_id')->nullable();
            $table->decimal('monto_venta', 22, 2);
            $table->decimal('monto_ticket', 22, 2);
            $table->char('estado', 1)->default('P');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->date('fecha_canje')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cajero_id')->nullable();
            $table->unsignedBigInteger('turno_operativo_estacionamiento_id')->nullable();
            $table->string('identificador_pc', 80)->nullable();
            $table->string('numerocupon', 40)->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_ticket_canje_caja_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('cliente_vip_caja_id', 'fk_ticket_canje_caja_cliente_vip')
                ->references('id')->on('cliente_vip_caja')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('venta_id', 'fk_ticket_canje_caja_venta')
                ->references('id')->on('venta')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('usuario_id', 'fk_ticket_canje_caja_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('cajero_id', 'fk_ticket_canje_caja_cajero')
                ->references('id')->on('usuario')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('turno_operativo_estacionamiento_id', 'fk_ticket_canje_caja_turno_op')
                ->references('id')->on('turno_operativo_estacionamiento')->onDelete('set null')->onUpdate('cascade');

            $table->unique(['empresa_id', 'movimiento_id', 'numero_ticket'], 'uq_ticket_canje_caja_serie');
            $table->index(['empresa_id', 'usuario_id', 'fecha'], 'idx_ticket_canje_caja_usuario');
            $table->index(['empresa_id', 'nro_documento', 'fecha'], 'idx_ticket_canje_caja_documento');
            $table->index(['empresa_id', 'estado'], 'idx_ticket_canje_caja_estado');
            $table->index('venta_id', 'idx_ticket_canje_caja_venta');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_canje_caja');
    }
};
