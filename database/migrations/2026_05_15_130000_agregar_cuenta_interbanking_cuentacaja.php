<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCuentaInterbankingCuentacaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cuentacaja', function (Blueprint $table) {
            $table->string('cuenta_interbanking', 255)->nullable()->after('cbu');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cuentacaja', function (Blueprint $table) {
            $table->dropColumn('cuenta_interbanking');
        });
    }
}
