<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedido extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fechaentrega');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_pedido_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('condicionventa_id')->nullable();
            $table->foreign('condicionventa_id', 'fk_pedido_condicionventa')->references('id')->on('condicionventa')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreign('vendedor_id', 'fk_pedido_vendedor')->references('id')->on('vendedor')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_pedido_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('mventa_id')->nullable();
            $table->foreign('mventa_id', 'fk_pedido_mventa')->references('id')->on('mventa')->onDelete('set null')->onUpdate('set null');
            $table->string('estado',1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_pedido_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->string('leyenda',255)->nullable();
            $table->float('descuento',5,2);
            $table->string('descuentointegrado',100);
            $table->string('lugarentrega',255)->nullable();
            $table->string('codigo',100);
            $table->string('estadopedido',80);
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
        Schema::dropIfExists('pedido');
    }
}
