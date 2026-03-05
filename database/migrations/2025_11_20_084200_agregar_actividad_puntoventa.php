<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarActividadPuntoventa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('puntoventa', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_arca_id')->after('pathafip')->nullable();
            $table->foreign('actividad_arca_id', 'fk_actividad_arca_puntoventa')->references('id')->on('actividad_arca')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('puntoventa', function (Blueprint $table) {
            $table->dropForeign('fk_actividad_arca_puntoventa');
            $table->dropColumn('actividad_arca_id');
        });
    }
}
