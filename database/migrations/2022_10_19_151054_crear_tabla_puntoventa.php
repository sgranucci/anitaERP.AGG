<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPuntoventa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('puntoventa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',50);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_puntoventa_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('domicilio',255);
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_puntoventa_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_puntoventa_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_puntoventa_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable(); 
            $table->string('email', 255)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('leyenda',255)->nullable();
            $table->string('modofacturacion',1);
            $table->string('estado',1);
            $table->string('webservice',50)->nullable();
            $table->string('pathafip',255)->nullable();
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('puntoventa');
    }
}
