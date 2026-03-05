<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarVentaIdOrdenventaCuota extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ordenventa_cuota', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->after('montofactura')->nullable();
            $table->foreign('venta_id', 'fk_ordenventa_cuota_venta')->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
        });    
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ordenventa_cuota', function (Blueprint $table) {
            $table->dropForeign('fk_ordenventa_cuota_venta');
            $table->dropColumn('venta_id');
        });
    }
}
