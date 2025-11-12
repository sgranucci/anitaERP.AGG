<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaListaprecio extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('listaprecio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 100);
            $table->string('formula', 255);
            $table->string('incluyeimpuesto', 1);
            $table->unsignedBigInteger('codigo');
            $table->string('desdetalle', 10);
            $table->string('hastatalle', 10);
            $table->unsignedBigInteger('tiponumeracion_id')->nullable();
            $table->foreign('tiponumeracion_id', 'fk_listaprecio_tiponumeracion')->references('id')->on('tiponumeracion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('usuarioultcambio_id');
            $table->foreign('usuarioultcambio_id', 'fk_listaprecio_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('listaprecio');
    }
}
