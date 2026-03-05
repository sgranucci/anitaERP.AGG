<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteArchivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_archivo_cliente')->references('id')->on('cliente')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo',255);
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
        Schema::dropIfExists('cliente_archivo');
    }
}
