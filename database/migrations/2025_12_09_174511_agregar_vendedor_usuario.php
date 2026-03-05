<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarVendedorUsuario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->unsignedBigInteger('vendedor_id')->after('centrocosto_id')->nullable();
            $table->foreign('vendedor_id', 'fk_vendedor_usuario')->references('id')->on('vendedor')->onDelete('restrict')->onUpdate('restrict');
        });   
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign('fk_vendedor_usuario');
            $table->dropColumn('vendedor_id');
        });
    }
}
