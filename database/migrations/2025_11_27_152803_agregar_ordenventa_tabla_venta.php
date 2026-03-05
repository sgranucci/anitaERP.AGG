<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarOrdenventaTablaVenta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->unsignedBigInteger('ordenventa_id')->after('cantidadbulto')->nullable();
            $table->foreign('ordenventa_id', 'fk_ordenventa_venta')->references('id')->on('ordenventa')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropForeign('fk_ordenventa_venta');
            $table->dropColumn('ordenventa_id');
        });
    }
}
