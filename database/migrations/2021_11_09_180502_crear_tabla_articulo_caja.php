<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArticuloCaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articulo_caja', function (Blueprint $table) {
			$table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_articulo_caja_articulo')->references('id')->on('articulo')->onDelete('cascade');
			$table->unsignedBigInteger('caja_id');
			$table->foreign('caja_id', 'fk_articulo_caja_cajaproducto')->references('id')->on('cajaproducto')->onDelete('cascade');
            $table->unsignedTinyInteger('desdenro');
            $table->unsignedTinyInteger('hastanro');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articulo_caja');
    }
}
