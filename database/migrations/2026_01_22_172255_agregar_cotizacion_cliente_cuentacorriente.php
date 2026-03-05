<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCotizacionClienteCuentacorriente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_cuentacorriente', function (Blueprint $table) {
            $table->float('cotizacion')->after('moneda_id');
        });  
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente_cuentacorriente', function (Blueprint $table) {
            $table->dropColumn('cotizacion');
        });
    }
}
