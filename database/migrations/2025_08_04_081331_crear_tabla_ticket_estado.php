<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicketEstado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->foreign('ticket_id', 'fk_ticket_estado_ticket')->references('id')->on('ticket')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('estado', 50);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_ticket_estado_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('observacion');
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
        Schema::dropIfExists('ticket_estado');
    }
}
