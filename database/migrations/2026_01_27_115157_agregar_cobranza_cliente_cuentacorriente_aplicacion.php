<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCobranzaClienteCuentacorrienteAplicacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            $table->foreign('cobranza_id', 'fk_cliente_cuentacorriente_aplicacion_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
        }); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_cuentacorriente_aplicacion_cobranza');
        });
    }
}
