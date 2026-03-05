<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedidoCombinacionTalle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('pedido_combinacion_talle', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->unsignedBigInteger('pedido_combinacion_id');
            $table->foreign('pedido_combinacion_id', 'fk_pedido_combinacion_talle_pedido_combinacion')->references('id')->on('pedido_combinacion')->onDelete('cascade');
			$table->unsignedBigInteger('talle_id');
			$table->foreign('talle_id', 'fk_pedido_combinacion_talle_talle')->references('id')->on('talle')->onDelete('restrict');
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
        Schema::dropIfExists('pedido_combinacion_talle');
    }
}
