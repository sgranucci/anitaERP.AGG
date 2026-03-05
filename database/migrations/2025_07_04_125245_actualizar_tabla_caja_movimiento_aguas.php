<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ActualizarTablaCajaMovimientoAguas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
			$table->unsignedBigInteger('ordenservicio_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->dropColumn('ordenservicio_id');
        });
    }
}
