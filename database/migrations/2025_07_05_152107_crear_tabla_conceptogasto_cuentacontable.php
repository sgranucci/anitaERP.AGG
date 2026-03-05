<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaConceptogastoCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conceptogasto_cuentacontable', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conceptogasto_id');
            $table->foreign('conceptogasto_id', 'fk_conceptogasto_cuentacontable_conceptogasto')->references('id')->on('conceptogasto')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_conceptogasto_cuentacontable_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('conceptogasto_cuentacontable');  
    }
}
