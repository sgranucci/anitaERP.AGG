<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCajaPiezaVentaEmision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (strtoupper(config('app.empresa')) == 'EL BIERZO')
            Schema::table('venta_emision', function (Blueprint $table) {
                $table->decimal('pieza', 20, 6)->after('cantidad')->nullable();
                $table->decimal('caja', 20, 6)->after('pieza')->nullable();
            });   
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (strtoupper(config('app.empresa')) == 'EL BIERZO')
            Schema::table('venta_emision', function (Blueprint $table) {
                $table->dropColumn('caja');
                $table->dropColumn('pieza');
            });
    }
}
