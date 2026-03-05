<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecrearTablaOrdentrabajoCombinacionTalle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('ordentrabajo_combinacion_talle');

        Schema::create('ordentrabajo_combinacion_talle', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ordentrabajo_id');
			$table->foreign('ordentrabajo_id', 'fk_ordentrabajo_combinacion_talle_ordentrabajo')->references('id')->on('ordentrabajo')->onDelete('cascade');
            $table->unsignedBigInteger('pedido_combinacion_talle_id');
            $table->foreign('pedido_combinacion_talle_id', 'fk_ordentrabajo_combinacion_talle_pedido_combinacion_talle')->references('id')->on('pedido_combinacion_talle')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_ordentrabajo_combinacion_talle_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado', 1);
            $table->unsignedBigInteger('ordentrabajo_stock_id')->nullable();
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_ordentrabajo_combinacion_talle_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->timestamps();
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
    }
}
