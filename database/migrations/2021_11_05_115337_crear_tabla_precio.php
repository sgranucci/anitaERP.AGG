<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPrecio extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('precio', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_precio_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('listaprecio_id');
            $table->foreign('listaprecio_id', 'fk_precio_listaprecio')->references('id')->on('listaprecio')->onDelete('restrict')->onUpdate('restrict');
            $table->date('fechavigencia')->nullable();
			$table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_precio_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
			$table->decimal('precio',20,6);
			$table->decimal('precioanterior',20,6);
            $table->unsignedBigInteger('usuarioultcambio_id');
            $table->foreign('usuarioultcambio_id', 'fk_precio_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('precio');
    }
}
