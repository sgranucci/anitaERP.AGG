<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedidoArticuloCaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido_articulo_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('pedido_id');
			$table->foreign('pedido_id', 'fk_pedido_articulo_caja_pedido')->references('id')->on('pedido')->onDelete('cascade');
			$table->unsignedBigInteger('pedido_articulo_id');
			$table->foreign('pedido_articulo_id', 'fk_pedido_articulo_caja_pedido_articulo')->references('id')->on('pedido_articulo')->onDelete('cascade');
			$table->unsignedBigInteger('numerocaja');
            $table->decimal('pieza',22,6);
            $table->decimal('kilo',22,6);
            $table->string('lote', 255);
            $table->date('fechavencimiento');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_pedido_articulo_caja_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('pedido_articulo_caja');
    }
}
