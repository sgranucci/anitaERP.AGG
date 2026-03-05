<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCodigosenasa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('codigosenasa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('registro',255);
            $table->unsignedBigInteger('envasesenasa_id')->nullable();
            $table->foreign('envasesenasa_id', 'fk_codigosenasa_envasesenasa')->references('id')->on('envasesenasa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('llevafrio',50);
            $table->string('prefijo',50);
            $table->string('codigo',50);
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
        Schema::dropIfExists('codigosenasa');
    }
}
