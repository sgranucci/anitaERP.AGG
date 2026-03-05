<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMovil extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movil', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('dominio',50)->nullale();
            $table->string('tipomovil',1);
            $table->string('codigo',10);
            $table->date('vencimientoverificacionmunicipal')->nullable();
            $table->date('vencimientoverificaciontecnica')->nullable();
            $table->date('vencimientoservice')->nullable();
            $table->date('vencimientocorredor')->nullable();
            $table->date('vencimientoingresoparque')->nullable();
            $table->date('vencimientoseguro')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('movil');     
    }
}
