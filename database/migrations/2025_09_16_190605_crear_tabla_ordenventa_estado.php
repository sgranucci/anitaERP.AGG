<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaOrdenventaEstado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenventa_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordenventa_id');
            $table->foreign('ordenventa_id', 'fk_ordenventa_estado_ordenventa')->references('id')->on('ordenventa')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('estado', 50);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_ordenventa_estado_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordenventa_estado');
    }
}
