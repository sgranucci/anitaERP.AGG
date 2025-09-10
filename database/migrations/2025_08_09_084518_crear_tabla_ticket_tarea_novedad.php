<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicketTareaNovedad extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_tarea_novedad', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ticket_tarea_id');
            $table->foreign('ticket_tarea_id', 'fk_ticket_tarea_novedad_ticket_tarea')->references('id')->on('ticket_tarea')->onDelete('cascade')->onUpdate('cascade');
            $table->datetime('desdefecha');
            $table->datetime('hastafecha');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_ticket_tarea_novedad_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('comentario');
            $table->string('estado', 50);
            $table->softDeletes();
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
        Schema::dropIfExists('ticket_tarea_novedad');
    }
}
