<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArticulocosto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articulo_costo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_costotarea_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('tarea_id');
			$table->foreign('tarea_id', 'fk_costotarea_tarea')->references('id')->on('tarea')->onDelete('cascade')->onUpdate('cascade');
			$table->decimal('costo',20,6);
            $table->date('fechaviencia')->nullable();
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
        Schema::dropIfExists('articulo_costo');
    }
}
