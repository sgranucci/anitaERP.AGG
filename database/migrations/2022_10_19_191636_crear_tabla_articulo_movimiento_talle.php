<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArticuloMovimientoTalle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articulo_movimiento_talle', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->unsignedBigInteger('articulo_movimiento_id');
            $table->foreign('articulo_movimiento_id', 'fk_articulo_movimiento_talle_articulo_movimiento')->references('id')->on('articulo_movimiento')->onDelete('cascade');
            $table->unsignedBigInteger('pedido_combinacion_talle_id')->nullable();
			$table->foreign('pedido_combinacion_talle_id', 'fk_articulo_movimiento_pedido_combinacion_talle')->references('id')->on('pedido_combinacion_talle')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('talle_id');
			$table->foreign('talle_id', 'fk_articulo_movimiento_talle_talle')->references('id')->on('talle')->onDelete('restrict');
			$table->decimal('cantidad',20,6);
			$table->decimal('precio',20,6);
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
        Schema::dropIfExists('articulo_movimiento_talle');    
    }
}
