<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTransporte extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transporte', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',50);
            $table->string('codigo',10);
            $table->string('domicilio',255);
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_transporte_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_transporte_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nroinscripcion',100)->nullable();
			$table->unsignedBigInteger('condicioniva_id');
            $table->foreign('condicioniva_id', 'fk_transporte_condicioniva')->references('id')->on('condicioniva')->onDelete('restrict')->onUpdate('restrict');
            $table->string('patentevehiculo', 20)->nullable();
            $table->string('patenteacoplado', 20)->nullable();
            $table->string('horarioentrega', 255)->nullable();
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
        Schema::dropIfExists('transporte');
    }
}
