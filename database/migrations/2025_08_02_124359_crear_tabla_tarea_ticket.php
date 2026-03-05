<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTareaTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tarea_ticket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('tipotarea',1);
            $table->unsignedBigInteger('areadestino_id');
            $table->foreign('areadestino_id', 'fk_tarea_ticket_areadestino')->references('id')->on('areadestino')->onDelete('restrict')->onUpdate('restrict');
            $table->float('tiempoestimado');   
            $table->string('enviacorreo',1);
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
        Schema::dropIfExists('tarea_ticket');
    }
}
