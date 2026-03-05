<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCajaMovimientoArchivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('caja_movimiento_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('caja_movimiento_id');
            $table->foreign('caja_movimiento_id', 'fk_caja_movimiento_archivo_caja_movimiento')->references('id')->on('caja_movimiento')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo',255);
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
        Schema::dropIfExists('caja_movimiento_archivo');
    }
}
