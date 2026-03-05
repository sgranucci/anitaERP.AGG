<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarProvincianacimientoClienteUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
			$table->unsignedBigInteger('provincianacimiento_id')->after('fechanacimiento')->nullable();
            $table->foreign('provincianacimiento_id', 'fk_cliente_uif_provincianacimiento')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
			$table->dropForeign('fk_cliente_uif_provincianacimiento');
			$table->dropColumn('provincianacimiento_id');
        });
    }
}
