<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTipodocumentoCliente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('tipodocumento_id')->after('hastafecha_exclusionpercepcioniva')->nullable();
            $table->foreign('tipodocumento_id', 'fk_cliente_tipodocumento')->references('id')->on('tipodocumento')->onDelete('restrict')->onUpdate('restrict');
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
            $table->dropForeign('fk_cliente_tipodocumento');
            $table->dropColumn('tipodocumento_id');
        });
    }
}
