<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaFrecuenciaUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('frecuencia_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('desdeoperacion');
            $table->unsignedBigInteger('hastaoperacion');
            $table->string('riesgo', 50);
            $table->unsignedBigInteger('puntaje');
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
        Schema::dropIfExists('frecuencia_uif');
    }
}
