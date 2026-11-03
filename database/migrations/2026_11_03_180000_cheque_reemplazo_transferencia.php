<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canje: el cheque anulado se reemplaza por una transferencia
 * (sale o entra por una cuenta de banco, sin número de cheque nuevo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cheque_reemplazo_transferencia')) {
            return;
        }

        Schema::create('cheque_reemplazo_transferencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_movimiento_id');
            $table->unsignedBigInteger('cheque_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->date('fecha')->nullable();
            $table->decimal('monto', 18, 2);
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('cotizacion', 18, 6)->default(1);
            $table->timestamps();

            $table->unique(['caja_movimiento_id', 'cheque_id'], 'cheque_reemplazo_trf_mov_cheque_uq');
            $table->index('cheque_id', 'cheque_reemplazo_trf_cheque_idx');

            $table->foreign('caja_movimiento_id', 'fk_cheque_reemplazo_trf_mov')
                ->references('id')->on('caja_movimiento')->cascadeOnDelete();
            $table->foreign('cheque_id', 'fk_cheque_reemplazo_trf_cheque')
                ->references('id')->on('cheque')->restrictOnDelete();
            $table->foreign('cuentacaja_id', 'fk_cheque_reemplazo_trf_cuenta')
                ->references('id')->on('cuentacaja')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_reemplazo_transferencia');
    }
};
