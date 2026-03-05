<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCamposArticuloAgg extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->text('leyenda')->after('sectorsellado_id')->nullable();
            $table->float('coeficienteconversion')->after('leyenda')->nullable();
            $table->unsignedBigInteger('depositoentrega_id')->after('coeficienteconversion')->nullable();
            $table->foreign('depositoentrega_id', 'fk_articulo_deposito_entrega')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->string('numeroparte', 50)->after('depositoentrega_id')->nullable();    
            $table->string('ubicacionparte', 50)->after('numeroparte')->nullable(); 
            $table->unsignedBigInteger('oficinacompra_id')->after('ubicacionparte')->nullable();
            $table->foreign('oficinacompra_id', 'fk_articulo_oficinacompra')->references('id')->on('oficinacompra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('periodicidadcompra_id')->after('oficinacompra_id')->nullable();
            $table->foreign('periodicidadcompra_id', 'fk_articulo_periodicidadcompra')->references('id')->on('periodicidadcompra')->onDelete('restrict')->onUpdate('restrict');
            $table->string('detalle', 255)->nullable()->change();
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
            $table->dropForeign('fk_articulo_periodicidadcompra');
            $table->dropColumn('periodicidadcompra_id');
            $table->dropForeign('fk_articulo_oficinacompra');
            $table->dropColumn('oficinacompra_id');            
            $table->dropColumn('ubicacionparte');   
            $table->dropColumn('numeroparte');  
            $table->dropForeign('fk_articulo_deposito_entrega'); 
            $table->dropColumn('depositoentrega_id');
            $table->dropColumn('coeficienteconversion');
            $table->dropColumn('leyenda');
        });
    }
}
