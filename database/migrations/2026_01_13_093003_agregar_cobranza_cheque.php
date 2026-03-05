<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCobranzaCheque extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cheque', function (Blueprint $table) {
            $table->unsignedBigInteger('cobranza_id')->after('caja_movimiento_id')->nullable();
            $table->foreign('cobranza_id', 'fk_cheque_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
        }); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cheque', function (Blueprint $table) {
            $table->dropForeign('fk_cheque_cobranza');
            $table->dropColumn('cobranza_id');
        });
    }
}
