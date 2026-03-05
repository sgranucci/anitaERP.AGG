<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarNombreIaConceptoIvacompra extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('concepto_ivacompra', function (Blueprint $table) {
            $table->string('nombre_ia', 255)->after('impuesto_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('concepto_ivacompra', function (Blueprint $table) {
            $table->dropColumn('nombre_ia');
        });
    }
}
