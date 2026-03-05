<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->datetime('fecha');
            $table->unsignedBigInteger('sala_id');
            $table->foreign('sala_id', 'fk_ticket_sala')->references('id')->on('sala')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('subcategoria_ticket_id');
            $table->foreign('subcategoria_ticket_id', 'fk_ticket_subcategoria_ticket')->references('id')->on('subcategoria_ticket')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('areadestino_id');
            $table->foreign('areadestino_id', 'fk_ticket_areadestino')->references('id')->on('areadestino')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('sector_id');
            $table->foreign('sector_id', 'fk_ticket_sector_ticket')->references('id')->on('sector_ticket')->onDelete('restrict')->onUpdate('restrict');
            $table->string('detalle',255);
            $table->unsignedBigInteger('bienuso_id');
            $table->text('observacion');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_ticket_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ticket');
    }
}
