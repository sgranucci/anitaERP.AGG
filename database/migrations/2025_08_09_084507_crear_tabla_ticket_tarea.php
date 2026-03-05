<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTicketTarea extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_tarea', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ticket_id');
            $table->foreign('ticket_id', 'fk_ticket_tarea_ticket')->references('id')->on('ticket')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('tarea_id')->nullable();
            $table->foreign('tarea_id', 'fk_ticket_tarea_tarea_ticket')->references('id')->on('tarea_ticket')->onDelete('restrict')->onUpdate('restrict');
            $table->string('detalle', 255)->nullable();
            $table->date('fechacarga');
            $table->date('fechaprogramacion');
            $table->date('fechafinalizacion')->nullable();
            $table->decimal('tiempoinsumido',4,2)->nullable();
            $table->unsignedBigInteger('tecnico_id')->nullable();
            $table->foreign('tecnico_id', 'fk_ticket_tarea_tecnico_ticket')->references('id')->on('tecnico_ticket')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('turno_id')->nullable();
            $table->foreign('turno_id', 'fk_ticket_tarea_turno_ticket')->references('id')->on('turno_ticket')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ticket_tarea_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ticket_tarea');
    }
}
