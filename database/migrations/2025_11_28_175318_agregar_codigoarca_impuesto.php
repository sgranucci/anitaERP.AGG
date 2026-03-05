<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCodigoarcaImpuesto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('impuesto', function (Blueprint $table) {
            $table->string('codigoarca', 50)->after('codigo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('impuesto', function (Blueprint $table) {
            $table->dropColumn('codigoarca');
        });
    }
}
