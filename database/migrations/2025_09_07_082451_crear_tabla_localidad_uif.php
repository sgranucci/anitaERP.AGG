<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLocalidadUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('localidad_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->string('codigopostal', 50)->nullable();
            $table->unsignedBigInteger('provincia_uif_id')->nullable();
            $table->foreign('provincia_uif_id', 'fk_localidad_uif_provincia_uif')->references('id')->on('provincia_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigo', 10);
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
        Schema::dropIfExists('localidad_uif');
    }
}
