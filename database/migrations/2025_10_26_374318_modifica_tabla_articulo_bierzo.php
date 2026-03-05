<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificaTablaArticuloBierzo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->string('unidadmedidanomenclador', 50)->after('fechaultimacompra')->nullable();
            $table->string('codigobarra', 50)->after('unidadmedidanomenclador')->nullable();
            $table->unsignedInteger('unidadreferenciacodigobarra')->after('codigobarra')->nullable();
            $table->string('enviaalarma', 50)->after('unidadreferenciacodigobarra')->nullable();
            $table->unsignedInteger('grupocarne')->after('enviaalarma')->nullable();
            $table->unsignedInteger('tipocarne')->after('grupocarne')->nullable();
            $table->decimal('pesocaja', 22, 4)->after('tipocarne')->nullable();
            $table->decimal('alertastock', 22, 4)->after('pesocaja')->nullable();
            $table->string('origenproducto', 50)->after('alertastock')->nullable();
            $table->string('inicialproduccion', 50)->after('origenproducto')->nullable();
            $table->unsignedInteger('diasproceso')->after('inicialproduccion')->nullable();
            $table->unsignedInteger('vencimientoendia')->after('diasproceso')->nullable();
            $table->unsignedInteger('diaenfriado')->after('vencimientoendia')->nullable();
            $table->unsignedBigInteger('codigosenasa_id')->after('diaenfriado')->nullable();
            $table->foreign('codigosenasa_id', 'fk_articulo_codigosenasa')->references('id')->on('codigosenasa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('salaproduccion_id')->after('codigosenasa_id')->nullable();
            $table->foreign('salaproduccion_id', 'fk_articulo_salaproduccion')->references('id')->on('salaproduccion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipoproduccion_id')->after('salaproduccion_id')->nullable();
            $table->foreign('tipoproduccion_id', 'fk_articulo_tipoproduccion')->references('id')->on('tipoproduccion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('sectorsellado_id')->after('tipoproduccion_id')->nullable();
            $table->foreign('sectorsellado_id', 'fk_articulo_sectorsellado')->references('id')->on('sectorsellado')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipoarticulo_id')->nullable();
            $table->foreign('tipoarticulo_id', 'fk_articulo_tipoarticulo')->references('id')->on('tipoarticulo')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_tipoarticulo');
            $table->dropColumn('tipoarticulo_id');
            $table->dropForeign('fk_articulo_sectorsellado');
            $table->dropColumn('sectorsellado_id');
            $table->dropForeign('fk_articulo_tipoproduccion');
            $table->dropColumn('tipoproduccion_id');
            $table->dropForeign('fk_articulo_salaproduccion');
            $table->dropColumn('salaproduccion_id');
            $table->dropForeign('fk_articulo_codigosenasa');
            $table->dropColumn('codigosenasa_id');
            $table->dropColumn('diaenfriado');
            $table->dropColumn('vencimientoendia');
            $table->dropColumn('diasproceso');
            $table->dropColumn('inicialproduccion');
            $table->dropColumn('origenproducto');
            $table->dropColumn('alertastock');
            $table->dropColumn('pesocaja');
            $table->dropColumn('tipocarne');
            $table->dropColumn('grupocarne');
            $table->dropColumn('enviaalarma');
            $table->dropColumn('unidadreferenciacodigobarra');
            $table->dropColumn('codigobarra');
            $table->dropColumn('unidadmedidanomenclador');
        });
    }
}
