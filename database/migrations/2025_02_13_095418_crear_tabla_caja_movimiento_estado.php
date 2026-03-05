<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCajaMovimientoEstado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('caja_movimiento_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_movimiento_id');
            $table->foreign('caja_movimiento_id', 'fk_caja_movimiento_estado_caja_movimiento')->references('id')->on('caja_movimiento')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('estado', 1);
            $table->string('observacion', 255);
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
        Schema::dropIfExists('caja_movimiento_estado');
    }
}
