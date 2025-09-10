<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteArchivoUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_archivo_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_uif_id');
            $table->foreign('cliente_uif_id', 'fk_cliente_archivo_uif_cliente')->references('id')->on('cliente_uif')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('cliente_archivo_uif');
    }
}
