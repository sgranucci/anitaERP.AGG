<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaSeteosalida extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seteosalida', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_seteosalida_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('salida_id');
            $table->foreign('salida_id', 'fk_seteosalida_salida')->references('id')->on('salida')->onDelete('cascade')->onUpdate('cascade');
            $table->string('programa',255);
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
        Schema::dropIfExists('seteosalida');
    }
}
