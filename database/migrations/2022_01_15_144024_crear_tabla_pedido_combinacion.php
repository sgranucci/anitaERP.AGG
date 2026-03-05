<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPedidoCombinacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido_combinacion', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('pedido_id');
			$table->foreign('pedido_id', 'fk_pedido_articulo_pedido')->references('id')->on('pedido')->onDelete('cascade');
			$table->unsignedBigInteger('combinacion_id');
			$table->foreign('combinacion_id', 'fk_pedido_articulo_combinacion')->references('id')->on('combinacion')->onDelete('cascade');
			$table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_pedido_articulo_articulo')->references('id')->on('articulo')->onDelete('cascade');
            $table->unsignedBigInteger('numeroitem');
			$table->unsignedBigInteger('modulo_id');
			$table->foreign('modulo_id', 'fk_pedido_articulo_modulo')->references('id')->on('modulo')->onDelete('restrict')->onUpdate('restrict');
			$table->decimal('cantidad',20,6);
			$table->decimal('precio',20,6);
            $table->unsignedBigInteger('listaprecio_id');
            $table->foreign('listaprecio_id', 'fk_pedido_articulo_listaprecio')->references('id')->on('listaprecio')->onDelete('restrict')->onUpdate('restrict');
            $table->string('incluyeimpuesto', 1);
			$table->unsignedBigInteger('moneda_id');
			$table->foreign('moneda_id', 'fk_pedido_articulo_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
			$table->decimal('descuento',5,2);
            $table->string('descuentointegrado',100)->nullable();
			$table->unsignedBigInteger('categoria_id')->nullable();
			$table->foreign('categoria_id', 'fk_pedido_articulo_categoria')->references('id')->on('categoria')->onUpdate('set null')->onDelete('set null');
			$table->unsignedBigInteger('subcategoria_id')->nullable();
			$table->foreign('subcategoria_id', 'fk_pedido_articulo_subcategoria')->references('id')->on('subcategoria')->onUpdate('set null')->onDelete('set null');
			$table->unsignedBigInteger('linea_id')->nullable();
			$table->foreign('linea_id', 'fk_pedido_articulo_linea')->references('id')->on('linea')->onUpdate('set null')->onDelete('set null');
            $table->unsignedBigInteger('ot_id')->nullable;
            $table->unsignedBigInteger('lote_id')->nullable();
			$table->foreign('lote_id', 'fk_pedido_combinacion_lote')->references('id')->on('lote')->onUpdate('restrict')->onDelete('restrict');
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
        Schema::dropIfExists('pedido_combinacion');
    }
}
