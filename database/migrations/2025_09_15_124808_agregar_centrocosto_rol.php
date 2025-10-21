<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCentrocostoRol extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rol', function (Blueprint $table) {
			$table->unsignedBigInteger('centrocosto_id')->after('nombre')->nullable();
            $table->foreign('centrocosto_id', 'fk_rol_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rol', function (Blueprint $table) {
			$table->dropForeign('fk_rol_centrocosto');
			$table->dropColumn('centrocosto_id');
        });
    }
}
