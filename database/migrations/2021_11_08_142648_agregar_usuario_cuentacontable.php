<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarUsuarioCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cuentacontable', function (Blueprint $table) {
			$table->unsignedBigInteger('usuarioultcambio_id')->after('monetaria');
            $table->foreign('usuarioultcambio_id', 'fk_cuentacontable_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cuentacontable', function (Blueprint $table) {
			$table->dropForeign('fk_cuentacontable_usuario');
			$table->dropColumn('usuarioultcambio_id');
        });
    }
}
