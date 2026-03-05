<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCobranzaAsiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('asiento', function (Blueprint $table) {
            $table->unsignedBigInteger('cobranza_id')->after('movimientostock_id')->nullable();
            $table->foreign('cobranza_id', 'fk_asiento_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
        });  
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asiento', function (Blueprint $table) {
            $table->dropForeign('fk_asiento_cobranza');
            $table->dropColumn('cobranza_id');
        });
    }
}
