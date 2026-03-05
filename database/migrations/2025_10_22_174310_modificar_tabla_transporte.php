<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificarTablaTransporte extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transporte', function (Blueprint $table) {
			$table->string('tipoexpreso')->after('horarioentrega')->nullable();
            $table->integer('copiaremito')->unsigned()->after('tipoexpreso')->nullable();
            $table->integer('copiapedido')->unsigned()->after('copiaremito')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transporte', function (Blueprint $table) {
			$table->dropColumn('tipoexpreso');
            $table->dropColumn('copiaremito');
            $table->dropColumn('copiapedido');
        });
    }
}
