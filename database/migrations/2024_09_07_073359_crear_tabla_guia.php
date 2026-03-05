<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaGuia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('tipodocumento',10)->nullale();
            $table->string('numerodocumento',100)->nullable();
            $table->string('maneja',1);
            $table->string('tipoguia',1);
            $table->string('carnetguia',50)->nullable();
            $table->string('carnetconducir',50)->nullable();
            $table->string('categoriacarnetconducir',50)->nullable();
            $table->string('carnetsanidad',50)->nullable();
            $table->string('observacion',255)->nullable();
            $table->string('codigo',10);
            $table->string('email', 255)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('domicilio',255)->nullable();
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_guia_localidad')->references('id')->on('localidad');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_guia_provincia')->references('id')->on('provincia');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_guia_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();
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
        Schema::dropIfExists('guia');
    }
}
