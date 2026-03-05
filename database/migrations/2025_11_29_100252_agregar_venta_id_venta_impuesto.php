<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarVentaIdVentaImpuesto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venta_impuesto', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->after('id');
            $table->foreign('venta_id', 'fk_venta_impuesto_venta')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
        });            
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venta_impuesto', function (Blueprint $table) {
            $table->dropForeign('fk_venta_impuesto_venta');
            $table->dropColumn('venta_id');
        });
    }
}
