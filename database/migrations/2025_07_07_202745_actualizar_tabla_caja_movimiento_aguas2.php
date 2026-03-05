<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ActualizarTablaCajaMovimientoAguas2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('rendicionreceptivo_id')->nullable();
            $table->foreign('rendicionreceptivo_id', 'fk_caja_movimiento_rendicionreceptivo')->references('id')->on('rendicionreceptivo')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_caja_movimiento_rendicionreceptivo');
            $table->dropColumn('rendicionreceptivo_id');
        });
    }
}
