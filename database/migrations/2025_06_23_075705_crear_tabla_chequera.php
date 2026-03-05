<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaChequera extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequera', function (Blueprint $table) {
            $table->id();
            $table->string('tipochequera', 1); // fisica / electronica
            $table->string('tipocheque', 1); // normal / diferido
            $table->string('codigo', 10); // Codigo anita
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_chequera_cuentacaja')->references('id')->on('cuentacaja')->onDelete('cascade')->onUpdate('cascade');
            $table->string('estado', 1);
            $table->date('fechauso')->nullable();
            $table->string('desdenumerocheque',50);
            $table->string('hastanumerocheque',50);
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
        Schema::dropIfExists('chequera');
    }
}
