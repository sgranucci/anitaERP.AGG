<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCondicionentregaArticulo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->unsignedBigInteger('condicionentrega_id')->after('periodicidadcompra_id')->nullable();
            $table->foreign('condicionentrega_id', 'fk_articulo_condicionentrega')->references('id')->on('condicionentrega')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_condicionentrega');
            $table->dropColumn('condicionentrega_id');
        });
    }
}
