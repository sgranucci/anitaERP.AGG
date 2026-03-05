<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaComisionServicioterrestre extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comision_servicioterrestre', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('servicioterrestre_id');
            $table->foreign('servicioterrestre_id', 'fk_comision_servicioterrestre_servicioterrestre')->references('id')->on('servicioterrestre')->onDelete('restrict')->onUpdate('restrict');
            $table->string('tipocomision', 2);
            $table->unsignedBigInteger('formapago_id');
            $table->foreign('formapago_id', 'fk_comision_servicioterrestre_formapago')->references('id')->on('formapago')->onDelete('restrict')->onUpdate('restrict');
            $table->float('porcentajecomision',5,2);
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
        Schema::dropIfExists('comision_servicioterrestre');
    }
}
