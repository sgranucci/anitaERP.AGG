<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedidoCombinacionEstado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido_combinacion_estado', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->unsignedBigInteger('pedido_combinacion_id');
            $table->foreign('pedido_combinacion_id', 'fk_pedido_combinacion_estado_pedido_combinacion')->references('id')->on('pedido_combinacion')->onDelete('cascade');
			$table->unsignedBigInteger('motivocierrepedido_id');
			$table->foreign('motivocierrepedido_id', 'fk_pedido_combinacion_estado_motivocierrepedido')->references('id')->on('motivocierrepedido')->onDelete('restrict');
			$table->unsignedBigInteger('cliente_id')->nullable();
			$table->foreign('cliente_id', 'fk_pedido_combinacion_estado_cliente')->references('id')->on('cliente');
			$table->string('estado', 1);
            $table->string('observacion', 255);
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
        Schema::dropIfExists('pedido_combinacion_estado');
    }
}
