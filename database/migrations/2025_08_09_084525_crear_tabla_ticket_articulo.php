<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicketArticulo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ticket_id');
            $table->foreign('ticket_id', 'fk_ticket_articulo_ticket')->references('id')->on('ticket')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_ticket_articulo_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cantidad',22,4);
            $table->unsignedBigInteger('requisicion_id')->nullable();
            $table->unsignedBigInteger('recepcion_id')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ticket_articulo_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ticket_articulo');
    }
}
