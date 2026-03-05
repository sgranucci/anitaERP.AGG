<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCodigoEmpresa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
			$table->dropColumn('domicilio');
			$table->dropColumn('nroinscripcion');
        });

        Schema::table('empresa', function (Blueprint $table) {
			$table->string('domicilio', 100)->nullable()->after('nombre');
			$table->string('nroinscripcion', 50)->nullable()->after('domicilio');
        });

        Schema::table('empresa', function (Blueprint $table) {
			$table->unsignedBigInteger('codigo')->after('nroinscripcion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
    		$table->dropColumn('codigo');        
        });
    }
}
