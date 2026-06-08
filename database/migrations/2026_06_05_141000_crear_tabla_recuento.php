<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de recuentos de inventario por depósito.
 *
 * Estados: PENDIENTE, SUSPENDIDO, CERRADO_PARCIAL, CERRADO_TOTAL, ANULADO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recuento')) {
            return;
        }

        Schema::create('recuento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30)->nullable();
            $table->date('fecha');
            $table->unsignedBigInteger('deposito_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('estado', 30)->default('PENDIENTE');
            $table->string('tipo', 20)->default('MANUAL')->comment('MANUAL|ALEATORIO|IMPORTADO');
            $table->unsignedInteger('cantidad_aleatoria')->nullable();
            $table->text('comentario')->nullable();
            $table->unsignedBigInteger('movimientostock_cierre_id')->nullable();
            $table->unsignedBigInteger('movimientostock_anulacion_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('deposito_id', 'fk_recuento_deposito')
                ->references('id')->on('depmae')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('empresa_id', 'fk_recuento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('usuario_id', 'fk_recuento_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('movimientostock_cierre_id', 'fk_recuento_mov_cierre')
                ->references('id')->on('movimientostock')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('movimientostock_anulacion_id', 'fk_recuento_mov_anulacion')
                ->references('id')->on('movimientostock')
                ->onDelete('set null')->onUpdate('restrict');

            $table->index(['deposito_id', 'estado'], 'ix_recuento_deposito_estado');
            $table->index('fecha', 'ix_recuento_fecha');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuento');
    }
};
