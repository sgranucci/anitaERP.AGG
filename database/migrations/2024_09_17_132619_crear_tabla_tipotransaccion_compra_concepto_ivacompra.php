<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTipotransaccionCompraConceptoIvaCompra extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipotransaccion_compra_concepto_ivacompra', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tipotransaccion_compra_id');
            $table->foreign('tipotransaccion_compra_id', 'fk_tipotransaccion_compra_concivacompra_tipotransaccion_compra')->references('id')->on('tipotransaccion_compra')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('concepto_ivacompra_id');
            $table->foreign('concepto_ivacompra_id', 'fk_tipotransaccion_compra_concepto_ivacompra')->references('id')->on('concepto_ivacompra')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipotransaccion_compra_concepto_ivacompra');
    }
}
