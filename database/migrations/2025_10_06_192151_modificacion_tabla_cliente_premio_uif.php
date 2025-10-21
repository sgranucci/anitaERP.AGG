<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificacionTablaClientePremioUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_premio_uif', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_premio_uif_mediopago');
			$table->dropColumn('mediopago_id');
			$table->unsignedBigInteger('formapago_id')->after('fechatito')->nullable();
            $table->foreign('formapago_id', 'fk_cliente_premio_formapago')->references('id')->on('formapago')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
