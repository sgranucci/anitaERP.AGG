<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCamposCobranza extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cobranza', function (Blueprint $table) {
            $table->string('estado', 50)->nullable()->after('detalle');
            $table->decimal('monto', 22, 4)->after('estado');
            $table->float('cotizacion')->after('monto');
            $table->unsignedBigInteger('moneda_id')->after('cotizacion');
            $table->foreign('moneda_id', 'fk_cobranza_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cobranza', function (Blueprint $table) {
            $table->dropColumn('estado');
            $table->dropColumn('monto');
            $table->dropColumn('cotizacion');
            $table->dropForeign('fk_cobranza_moneda');
            $table->dropColumn('moneda_id');
        });
    }
}
