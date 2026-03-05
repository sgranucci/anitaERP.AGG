<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCamposCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cuentacontable', function (Blueprint $table) {
            $table->dropColumn('cuentacontable_id');
            $table->dropColumn('orden');
            $table->string('ajustamonedaextranjera',1)->default('N')->after('manejaccosto');
			$table->unsignedBigInteger('conceptogasto_id')->nullable()->after('ajustamonedaextranjera');
            $table->foreign('conceptogasto_id', 'fk_cuentacontable_conceptogasto')->references('id')->on('conceptogasto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('cuentacontable_difcambio_id')->nullable()->after('conceptogasto_id');
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
            $table->dropColumn('cuentacontable_difcambio_id');
			$table->dropForeign('fk_cuentacontable_conceptogasto');
			$table->dropColumn('conceptogasto_id');
            $table->dropColumn('ajustamonedaextranjera');
        });
    }
}
