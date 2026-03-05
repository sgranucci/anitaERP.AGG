<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArticuloMovimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articulo_movimiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fechajornada');
            $table->unsignedBigInteger('tipotransaccion_id');
            $table->foreign('tipotransaccion_id', 'fk_articulo_movimiento_tipotransaccion')->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('venta_id')->nullable();
			$table->foreign('venta_id', 'fk_articulo_movimiento_venta')->references('id')->on('venta')->onDelete('cascade');
            $table->unsignedBigInteger('movimientostock_id')->nullable();
			$table->foreign('movimientostock_id', 'fk_articulo_movimiento_movimientostock')->references('id')->on('movimientostock')->onDelete('cascade');
            $table->unsignedBigInteger('pedido_combinacion_id')->nullable();
			$table->foreign('pedido_combinacion_id', 'fk_articulo_movimiento_pedido_combinacion')->references('id')->on('pedido_combinacion')->onDelete('cascade');
			$table->unsignedBigInteger('ordentrabajo_id')->nullable();
			$table->foreign('ordentrabajo_id', 'fk_articulo_movimiento_ordentrabajo')->references('id')->on('ordentrabajo')->onDelete('cascade');
			$table->unsignedBigInteger('lote');
            $table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_articulo_movimiento_articulo')->references('id')->on('articulo')->onDelete('restrict');
            $table->unsignedBigInteger('combinacion_id')->nullable();
			$table->foreign('combinacion_id', 'fk_articulo_movimiento_combinacion')->references('id')->on('combinacion')->onDelete('restrict');
            $table->string('concepto',255);
            $table->unsignedBigInteger('modulo_id')->nullable();
			$table->foreign('modulo_id', 'fk_articulo_movimiento_modulo')->references('id')->on('modulo');
            $table->decimal('cantidad',20,6);
            $table->decimal('precio',20,6);
            $table->decimal('costo',20,6);
            $table->unsignedBigInteger('listaprecio_id')->nullable();
            $table->foreign('listaprecio_id', 'fk_articulo_movimiento_listaprecio')->references('id')->on('listaprecio')->onDelete('restrict');
            $table->string('incluyeimpuesto', 1)->nullable();
			$table->unsignedBigInteger('moneda_id')->nullable();
			$table->foreign('moneda_id', 'fk_articulo_movimiento_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
			$table->decimal('descuento',5,2)->nullable();
            $table->string('descuentointegrado',100)->nullable();
            $table->unsignedBigInteger('deposito_id');
            $table->foreign('deposito_id', 'fk_articulo_movimiento_depmae')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('loteimportacion_id')->nullable();
            $table->foreign('loteimportacion_id', 'fk_articulo_movimiento_lote')->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articulo_movimiento');    
    }
}
