<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteArticuloSuspendido extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_articulo_suspendido', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_articulo_suspendido_cliente')->references('id')->on('cliente')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('articulo_id');
			$table->foreign('articulo_id', 'fk_cliente_articulo_suspendido_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_cliente_articulo_suspendido_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('cliente_articulo_suspendido');
    }
}
