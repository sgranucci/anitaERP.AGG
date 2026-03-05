<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCentrocostoUsuario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
			$table->unsignedBigInteger('centrocosto_id')->after('email')->nullable();
            $table->foreign('centrocosto_id', 'fk_usuario_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
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
			$table->dropForeign('fk_usuario_centrocosto');
			$table->dropColumn('centrocosto_id');
        });
    }
}
