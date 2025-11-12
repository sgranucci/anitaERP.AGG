<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedidoArticulo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('pedido_id');
			$table->foreign('pedido_id', 'fk_pedido_art_pedido')->references('id')->on('pedido')->onDelete('cascade');
			$table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_pedido_art_articulo')->references('id')->on('articulo')->onDelete('cascade');
			$table->unsignedBigInteger('unidadmedida_id')->nullable();
            $table->foreign('unidadmedida_id', 'fk_pedido_art_unidadmedida')->references('id')->on('unidadmedida')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('numeroitem');
			$table->decimal('caja',22,6);
            $table->decimal('pieza',22,6);
            $table->decimal('kilo',22,6);
            $table->decimal('pesada',22,6);
			$table->decimal('precio',22,6);
            $table->unsignedBigInteger('listaprecio_id');
            $table->foreign('listaprecio_id', 'fk_pedido_art_listaprecio')->references('id')->on('listaprecio')->onDelete('restrict')->onUpdate('restrict');
            $table->string('incluyeimpuesto', 1);
			$table->unsignedBigInteger('moneda_id');
			$table->foreign('moneda_id', 'fk_pedido_art_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
            $table->unsignedBigInteger('descuentoventa_id')->nullable();
            $table->foreign('descuentoventa_id', 'fk_pedido_art_descuentoventa')->references('id')->on('descuentoventa')->onDelete('restrict')->onUpdate('restrict');
			$table->decimal('descuento',5,2);
            $table->string('descuentointegrado',100)->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
			$table->foreign('lote_id', 'fk_pedido_art_lote')->references('id')->on('lote')->onUpdate('restrict')->onDelete('restrict');
            $table->string('observacion',255)->nullable();
            $table->string('estado',1)->nullable();
            $table->timestamps();
			$table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
			$table->index(['pedido_id', 'numeroitem']);
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pedido_articulo');
    }
}
