<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->float('nivelstock')->after('estado')->nullable();
            $table->datetime('fechaalta')->after('nivelstock')->nullable();
            $table->unsignedBigInteger('etiqueta_id')->after('nivelstock')->nullable();
            $table->float('unidadenvasado')->after('etiqueta_id')->nullable();
            $table->string('leyendanofacturar',255)->after('unidadenvasado')->nullable();
            $table->string('skuproveedor',50)->after('leyendanofacturar')->nullable();
            $table->string('skuproveedor2',50)->after('skuproveedor')->nullable();
            $table->string('posicionaracelaria',50)->after('skuproveedor2')->nullable();
            $table->string('vigenteenlista',1)->after('posicionaracelaria')->nullable();
			$table->unsignedBigInteger('cuentacontablevariacionprecio_id')->nullable()->after('vigenteenlista');
            $table->foreign('cuentacontablevariacionprecio_id', 'fk_articulo_cuentacontablevariacionprecio_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('centrocostovariacionprecio_id')->nullable();
            $table->foreign('centrocostovariacionprecio_id', 'fk_articulo_centrocostovariacionprecio_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocostocompra_id')->nullable();
            $table->foreign('centrocostocompra_id', 'fk_articulo_centrocostocompra_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->string('abc',1)->after('centrocostocompra_id')->nullable();
            $table->string('punto',10)->after('abc')->nullable();
            $table->string('lote',10)->after('punto')->nullable();
            $table->float('coeficientelitro')->after('lote')->nullable();
            $table->unsignedBigInteger('estadobloqueo_id')->after('coeficientelitro')->nullable();
            $table->string('estuche',50)->after('estadobloqueo_id')->nullable();
            $table->string('skuetiqueta',50)->after('estuche')->nullable();
            $table->string('skulistaprecio',50)->after('skuetiqueta')->nullable();
            $table->string('clase',1)->after('skulistaprecio')->nullable();
            $table->datetime('fechaprimeraventa')->after('clase')->nullable();
            $table->datetime('fechaprimeringreso')->after('fechaprimeraventa')->nullable();
            $table->string('estadofacturacion',50)->after('fechaprimeringreso')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->dropForeign('fk_articulo_cuentacontablevariacionprecio_cuentacontable');
            $table->dropForeign('fk_articulo_centrocostovariacionprecio_centrocosto');
            $table->dropForeign('fk_articulo_centrocostocompra_centrocosto');
            $table->dropColumn('nivelstock') ;
            $table->dropColumn('fechaalta') ;
            $table->dropColumn('etiqueta_id');
            $table->dropColumn('unidadenvasado');
            $table->dropColumn('leyendanofacturar');
            $table->dropColumn('skuproveedor');
            $table->dropColumn('skuproveedor2');
            $table->dropColumn('posicionaracelaria');
            $table->dropColumn('vigenteenlista');
			$table->dropColumn('cuentacontablevariacionprecio_id');
            $table->dropColumn('centrocostovariacionprecio_id');
            $table->dropColumn('centrocostocompra_id');
            $table->dropColumn('abc');
            $table->dropColumn('punto');
            $table->dropColumn('lote');
            $table->dropColumn('coeficientelitro');
            $table->dropColumn('estadobloqueo_id');
        });
    }
};
