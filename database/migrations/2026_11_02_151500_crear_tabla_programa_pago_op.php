<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre una celda del programa de pagos (proveedor + mes o TRANSF)
 * y la orden de pago generada. Evita generarla dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programa_pago_op')) {
            return;
        }

        Schema::create('programa_pago_op', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programa_pago_linea_id');
            $table->string('clave', 20);
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->timestamps();

            $table->foreign('programa_pago_linea_id', 'fk_pp_op_linea')
                ->references('id')->on('programa_pago_linea')->onDelete('restrict');
            $table->foreign('pagoproveedor_id', 'fk_pp_op_pago')
                ->references('id')->on('pagoproveedor')->onDelete('restrict');
            $table->unique(['programa_pago_linea_id', 'clave'], 'uq_pp_op_linea_clave');
            $table->index('pagoproveedor_id', 'ix_pp_op_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_pago_op');
    }
};
