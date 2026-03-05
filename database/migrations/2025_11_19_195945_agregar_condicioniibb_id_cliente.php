<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCondicioniibbIdCliente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('condicioniibb_id')->after('condicioniibb')->nullable();
            $table->foreign('condicioniibb_id', 'fk_cliente_condicionIIBB')->references('id')->on('condicionIIBB')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_condicionIIBB');
            $table->dropColumn('condicioniibb_id');
        });
    }
}
