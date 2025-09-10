<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTecnicoTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tecnico_ticket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_tecnico_ticket_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('areadestino_id');
            $table->foreign('areadestino_id', 'fk_tecnico_ticket_areadestino')->references('id')->on('areadestino')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('tecnico_ticket');
    }
}
