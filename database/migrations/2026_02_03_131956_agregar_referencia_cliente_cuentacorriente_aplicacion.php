<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarReferenciaClienteCuentacorrienteAplicacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_cuentacorriente_aplicado_id')->after('comprobanteaplicado')->nullable();
            $table->foreign('cliente_cuentacorriente_aplicado_id', 'fk_cliente_cuentacorriente_aplicacion2_cliente_cuentacorriente')->references('id')->on('cliente_cuentacorriente')->onDelete('cascade')->onUpdate('cascade');
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
            $table->dropForeign('fk_cliente_cuentacorriente_aplicacion_cliente_cuentacorriente');
            $table->dropColumn('cliente_cuentacorriente_aplicado_id');
        });
    }
}
