<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de cheques en cartera (CHT diferidos) a líneas del programa de pagos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programa_pago_cheque')) {
            return;
        }

        Schema::create('programa_pago_cheque', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programa_pago_linea_id');
            $table->string('clave', 16);
            $table->unsignedBigInteger('cheque_id');
            $table->decimal('monto_cheque', 18, 2)->default(0);
            $table->decimal('monto_programado', 18, 2)->default(0);
            $table->timestamps();

            $table->foreign('programa_pago_linea_id', 'fk_pp_cheque_linea')
                ->references('id')->on('programa_pago_linea')->onDelete('cascade');
            $table->foreign('cheque_id', 'fk_pp_cheque_cheque')
                ->references('id')->on('cheque')->onDelete('restrict');
            $table->unique(['cheque_id', 'programa_pago_linea_id', 'clave'], 'uq_pp_cheque_asig');
            $table->index(['programa_pago_linea_id', 'clave'], 'ix_pp_cheque_linea_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_pago_cheque');
    }
};
