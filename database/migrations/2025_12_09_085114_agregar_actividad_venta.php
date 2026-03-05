<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarActividadVenta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_arca_id')->after('numerocomprobante')->nullable();
            $table->foreign('actividad_arca_id', 'fk_venta_actividad_arca')->references('id')->on('actividad_arca')->onDelete('restrict')->onUpdate('restrict');
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
            $table->dropForeign('fk_venta_actividad_arca');
            $table->dropColumn('actividad_arca_id');
        });
    }
}
