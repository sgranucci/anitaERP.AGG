<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteSeguimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_seguimiento', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_seguimiento_cliente')->references('id')->on('cliente')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('observacion', 255)->nullable();
            $table->text('leyenda')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_cliente_seguimiento_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('cliente_seguimiento');
    }
}
