<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarDatosClienteBierzo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('distribuidor_id')->after('coeficiente_id')->nullable();
            $table->foreign('distribuidor_id', 'fk_cliente_distribuidor')->references('id')->on('distribuidor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('descuentoventa_id')->after('distribuidor_id')->nullable();
            $table->foreign('descuentoventa_id', 'fk_cliente_descuentoventa')->references('id')->on('descuentoventa')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn('distribuidor_id');
            $table->dropForeign('fk_cliente_distribuidor');
            $table->dropColumn('descuentoventa_id');
            $table->dropForeign('fk_cliente_descuentoventa');
        });
    }
}
