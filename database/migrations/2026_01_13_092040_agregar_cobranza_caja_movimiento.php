<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCobranzaCajaMovimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('cobranza_id')->after('conceptogasto_id')->nullable();
            $table->foreign('cobranza_id', 'fk_caja_movimiento_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
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
            $table->dropForeign('fk_caja_movimiento_cobranza');
            $table->dropColumn('cobranza_id');
        });
    }
}
