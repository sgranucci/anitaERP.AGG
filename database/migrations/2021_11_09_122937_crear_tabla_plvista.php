<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPlvista extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plvista', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',50);
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_plvista_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('plvista');
    }
}
