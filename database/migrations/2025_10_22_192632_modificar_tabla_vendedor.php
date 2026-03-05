<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificarTablaVendedor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vendedor', function (Blueprint $table) {
			$table->string('aplicasobre',50)->after('comisioncobranza')->nullable();
            $table->unsignedBigInteger('empresa_id')->after('aplicasobre')->nullable();
            $table->foreign('empresa_id', 'fk_vendedor_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('legajo_id')->after('empresa_id')->nullable();
            $table->string('email', 255)->after('legajo_id')->nullable();
            $table->string('codigo', 50)->after('email')->nullable();
            $table->string('estado', 50)->after('codigo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vendedor', function (Blueprint $table) {
			$table->dropColumn('aplicasobre');
            $table->dropForeign('fk_vendedor_empresa');
            $table->dropColumn('empresa_id');
            $table->dropColumn('legajo_id');
            $table->dropColumn('email');
            $table->dropColumn('codigo');
            $table->dropColumn('estado');
        });
    }
}
