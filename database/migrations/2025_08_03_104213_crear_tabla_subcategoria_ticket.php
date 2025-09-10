<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaSubcategoriaTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subcategoria_ticket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->unsignedBigInteger('categoria_ticket_id');
            $table->foreign('categoria_ticket_id', 'fk_subcategoria_ticket_categoria_ticket')->references('id')->on('categoria_ticket')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('subcategoria_ticket');
    }
}
