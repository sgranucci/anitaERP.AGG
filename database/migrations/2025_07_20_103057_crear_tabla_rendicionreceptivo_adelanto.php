<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRendicionreceptivoAdelanto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rendicionreceptivo_adelanto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rendicionreceptivo_id');
            $table->foreign('rendicionreceptivo_id', 'fk_rendicionreceptivo_adelanto_rendicionreceptivo')->references('id')->on('rendicionreceptivo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('caja_movimiento_id');
            $table->foreign('caja_movimiento_id', 'fk_rendicionreceptivo_adelanto_caja_movimiento')->references('id')->on('caja_movimiento')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('rendicionreceptivo_adelanto');
    }
}
