<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarZonavtaPedido extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->unsignedBigInteger('zonavta_id')->after('vendedor_id')->nullable();
            $table->foreign('zonavta_id', 'fk_pedido_zonavta')->references('id')->on('zonavta')->onDelete('restrict')->onUpdate('restrict');
        });    
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->dropForeign('fk_pedido_zonavta');
            $table->dropColumn('zonavta_id');
        });
    }
}
