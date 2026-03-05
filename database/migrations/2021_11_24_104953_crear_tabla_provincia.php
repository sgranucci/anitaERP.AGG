<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProvincia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provincia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('abreviatura',10)->nullable();
            $table->string('jurisdiccion',50)->nullable();
            $table->string('codigo',50)->nullable();
            $table->unsignedBigInteger('pais_id')->nullable();
            $table->foreign('pais_id', 'fk_provincia_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict')->nullable();
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
        Schema::dropIfExists('provincia');
    }
}
