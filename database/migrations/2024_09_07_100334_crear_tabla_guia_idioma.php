<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaGuiaIdioma extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guia_idioma', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('guia_id');
            $table->foreign('guia_id', 'fk_guia_idioma_guia')->references('id')->on('guia')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('idioma_id');
            $table->foreign('idioma_id', 'fk_guia_idioma_idioma')->references('id')->on('idioma')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('guia_idioma');
    }
}
