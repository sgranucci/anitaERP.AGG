<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCategoriaTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categoria_ticket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->unsignedBigInteger('areadestino_id');
            $table->foreign('areadestino_id', 'fk_categoria_ticket_areadestino')->references('id')->on('areadestino')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('categoria_ticket');
    }
}
