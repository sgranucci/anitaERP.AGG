<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VentaEmision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('venta_emision', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_venta_emision_venta')->references('id')->on('venta')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('numeroitem');
            $table->unsignedBigInteger('pedido_combinacion_id')->nullable();
            $table->foreign('pedido_combinacion_id', 'fk_venta_emision_pedido_combinacion')->references('id')->on('pedido_combinacion')->onDelete('cascade');
            $table->unsignedBigInteger('ordentrabajo_id')->nullable();
            $table->foreign('ordentrabajo_id', 'fk_venta_emision_ordentrabajo')->references('id')->on('ordentrabajo')->onDelete('cascade');
            $table->unsignedBigInteger('lotestock');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_venta_emision_articulo')->references('id')->on('articulo')->onDelete('cascade');
            $table->unsignedBigInteger('combinacion_id');
            $table->foreign('combinacion_id', 'fk_venta_emision_combinacion')->references('id')->on('combinacion')->onDelete('cascade');
            $table->string('detalle',255);
            $table->unsignedBigInteger('modulo_id')->nullable();
            $table->foreign('modulo_id', 'fk_venta_emision_modulo')->references('id')->on('modulo')->onDelete('restrict');
            $table->unsignedBigInteger('talle_id');
            $table->foreign('talle_id', 'fk_venta_emision_talle')->references('id')->on('talle')->onDelete('restrict');
            $table->decimal('cantidad',20,6);
            $table->decimal('precio',20,6);
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id', 'fk_venta_emision_impuesto')->references('id')->on('impuesto')->onDelete('restrict');
            $table->string('incluyeimpuesto', 1)->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->foreign('moneda_id', 'fk_venta_emision_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
            $table->decimal('descuento',5,2)->nullable();
            $table->string('descuentointegrado',100)->nullable();
            $table->unsignedBigInteger('deposito_id');
            $table->foreign('deposito_id', 'fk_venta_emision_depmae')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('loteimportacion_id')->nullable();
            $table->foreign('loteimportacion_id', 'fk_venta_emision_lote')->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('venta_emision');    
    }
}
