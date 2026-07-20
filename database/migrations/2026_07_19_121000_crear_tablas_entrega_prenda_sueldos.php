<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega de indumentaria a empleados:
 *  - configuracion_indumentaria_sueldos: fila única con depósito origen y tipo de
 *    transacción de stock (reutiliza el circuito contable de movimientos de stock).
 *  - entrega_prenda_sueldos / entrega_prenda_articulo_sueldos: ledger de entregas
 *    (cabecera + líneas), vinculado al movimiento de stock generado.
 *  - empleado_talle_sueldos: perfil de talles del empleado por prenda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_indumentaria_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('deposito_id')->nullable();            // depmae origen
            $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable(); // tipo salida contable
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('entrega_prenda_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->date('fecha');
            $table->unsignedSmallInteger('anio');
            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable();
            $table->unsignedBigInteger('movimientostock_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'anio']);
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
        });

        Schema::create('entrega_prenda_articulo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entrega_id');
            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('prenda_articulo_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->unsignedBigInteger('talle_id')->nullable();
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->string('sku', 20)->nullable();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['prenda_id']);
            $table->foreign('entrega_id')->references('id')->on('entrega_prenda_sueldos')->onDelete('cascade');
            $table->foreign('prenda_id')->references('id')->on('prenda_sueldos');
        });

        Schema::create('empleado_talle_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('talle_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['empleado_id', 'prenda_id'], 'empleado_talle_prenda_unique');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->onDelete('cascade');
            $table->foreign('prenda_id')->references('id')->on('prenda_sueldos')->onDelete('cascade');
            $table->foreign('talle_id')->references('id')->on('talle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_talle_sueldos');
        Schema::dropIfExists('entrega_prenda_articulo_sueldos');
        Schema::dropIfExists('entrega_prenda_sueldos');
        Schema::dropIfExists('configuracion_indumentaria_sueldos');
    }
};
