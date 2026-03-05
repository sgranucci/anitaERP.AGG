<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarEmpresaClienteCuentacorriente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_cuentacorriente', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->after('cobranza_id')->nullable();
            $table->foreign('empresa_id', 'fk_cliente_cuentacorriente_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
