<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarMinimoCoeficienteProvincia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('provincia', function (Blueprint $table) {
            $table->float('minimocoeficientecm05')->after('codigoexterno')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('provincia', function (Blueprint $table) {
            $table->dropColumn('minimocoeficientecm05');
        });
    }
}
