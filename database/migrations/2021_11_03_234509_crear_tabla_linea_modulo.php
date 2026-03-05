<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLineaModulo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('linea_modulo', function (Blueprint $table) {
			$table->unsignedBigInteger('linea_id');
			$table->foreign('linea_id', 'fk_linea_modulo_linea')->references('id')->on('linea')->onDelete('cascade');
			$table->unsignedBigInteger('modulo_id');
			$table->foreign('modulo_id', 'fk_linea_modulo_modulo')->references('id')->on('modulo')->onDelete('cascade');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('linea_modulo');
    }
}
