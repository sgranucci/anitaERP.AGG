<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de préstamos de materiales entre depósitos.
 *
 * Estados:
 *  - BORRADOR: armando la transacción.
 *  - PENDIENTE_APROBACION: salió del depósito origen, esperando confirmación
 *    del receptor (administrador del depósito destino).
 *  - APROBADO: receptor aprobó; se generó el ingreso al depósito destino.
 *  - RECHAZADO: receptor rechazó; se reversa la salida.
 *  - DEVUELTO: el destinatario devolvió todos los ítems al origen.
 *  - DEVUELTO_PARCIAL: se devolvió una parte de los ítems.
 *  - CANCELADO: cancelado por el solicitante antes de aprobación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamo')) {
            return;
        }

        Schema::create('prestamo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30)->nullable();
            $table->date('fecha_prestamo');
            $table->date('fecha_devolucion_prometida');
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_devolucion_real')->nullable();

            $table->unsignedBigInteger('deposito_origen_id');
            $table->unsignedBigInteger('deposito_destino_id');
            $table->unsignedBigInteger('solicitante_id')
                ->comment('Usuario que arma el préstamo');
            $table->unsignedBigInteger('aprobador_id')->nullable()
                ->comment('Administrador del destino que aprobó');

            $table->string('estado', 30)->default('BORRADOR');
            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->unsignedBigInteger('movimientostock_salida_id')->nullable()
                ->comment('Movimiento de salida del depósito origen');
            $table->unsignedBigInteger('movimientostock_ingreso_id')->nullable()
                ->comment('Movimiento de ingreso al depósito destino');

            $table->date('ultimo_recordatorio_enviado_el')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('deposito_origen_id', 'fk_prestamo_dep_origen')
                ->references('id')->on('depmae')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('deposito_destino_id', 'fk_prestamo_dep_destino')
                ->references('id')->on('depmae')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('solicitante_id', 'fk_prestamo_solicitante')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('aprobador_id', 'fk_prestamo_aprobador')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('movimientostock_salida_id', 'fk_prestamo_mov_salida')
                ->references('id')->on('movimientostock')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('movimientostock_ingreso_id', 'fk_prestamo_mov_ingreso')
                ->references('id')->on('movimientostock')
                ->onDelete('set null')->onUpdate('restrict');

            $table->index(['deposito_destino_id', 'estado'], 'ix_prestamo_destino_estado');
            $table->index('fecha_devolucion_prometida', 'ix_prestamo_fecha_devol');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo');
    }
};
