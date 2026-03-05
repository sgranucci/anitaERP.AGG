<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaModuloTalle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('modulo_talle', function (Blueprint $table) {
			$table->unsignedBigInteger('modulo_id');
			$table->foreign('modulo_id', 'fk_modulo_talle_modulo')->references('id')->on('modulo')->onDelete('cascade');
			$table->unsignedBigInteger('talle_id');
			$table->foreign('talle_id', 'fk_modulo_talle_talle')->references('id')->on('talle')->onDelete('cascade');
    		$table->integer('cantidad');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('modulo_talle');
    }
}
