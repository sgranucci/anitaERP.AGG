<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificaTablaClienteBierzo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('abasto_id')->after('cajaespecial')->nullable();
            $table->foreign('abasto_id', 'fk_cliente_abasto')->references('id')->on('abasto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('coeficiente_id')->after('abasto_id')->nullable();
            $table->foreign('coeficiente_id', 'fk_cliente_coeficiente')->references('id')->on('coeficiente')->onDelete('restrict')->onUpdate('restrict');
            $table->float('porcentajelogistica')->after('coeficiente_id')->nullable();
            $table->string('emitecertificado', 50)->after('porcentajelogistica')->nullable();
            $table->string('emitenotadecredito', 50)->after('emitecertificado')->nullable();
            $table->float('coeficienteextra')->after('emitenotadecredito')->nullable();
            $table->string('agregabonificacion', 50)->after('coeficienteextra')->nullable();
            $table->date('desdefecha_exclusionpercepcioniva')->after('agregabonificacion')->nullable();
            $table->date('hastafecha_exclusionpercepcioniva')->after('desdefecha_exclusionpercepcioniva')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_abasto');
            $table->dropColumn('abasto_id');
            $table->dropForeign('fk_cliente_coeficiente');
            $table->dropColumn('coeficiente_id');
            $table->dropForeign('fk_cliente_coeficiente');
            $table->dropColumn('porcentajelogistica');
            $table->dropColumn('emitecertificado');
            $table->dropColumn('emitenotadecredito');
            $table->dropColumn('coeficienteextra');
            $table->dropColumn('agregabonificacion');
            $table->dropColumn('desdefecha_exclusionpercepcioniva');
            $table->dropColumn('hastafecha_exclusionpercepcioniva');
        });
    }
}
