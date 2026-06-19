<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicketTareaComentarioUsuario extends Migration
{
    public function up()
    {
        Schema::create('ticket_tarea_comentario_usuario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_tarea_id');
            $table->foreign('ticket_tarea_id', 'fk_tt_comentario_usuario_ticket_tarea')
                ->references('id')->on('ticket_tarea')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_tt_comentario_usuario_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('comentario');
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_tarea_comentario_usuario');
    }
}
