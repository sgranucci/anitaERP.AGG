<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPrecargaComprobanteProveedor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('precarga_comprobante_proveedor', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_precarga_comprobante_proveedor_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_precarga_comprobante_proveedor_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('tipotransaccion_compra_id');
            $table->foreign('tipotransaccion_compra_id', 'fk_precarga_comprobante_proveedor_tipotransaccion_compra')->references('id')->on('tipotransaccion_compra')->onDelete('restrict')->onUpdate('restrict');
            $table->string('letra', 1);
            $table->integer('sucursal');
            $table->integer('numerocomprobante');
            $table->date('fechafactura');
            $table->datetime('fecharecepcionemail');
            $table->date('fechavencimientocaicae');
            $table->string('numerocae', 50);
            $table->string('numeroordencompra', 50);
            $table->string('rutaalmacenamiento', 255);
            $table->integer('pararevisar');
            $table->decimal('subtotal', 22, 4);
            $table->decimal('total', 22, 4);
            $table->string('moneda', 50);
			$table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_precarga_comprobante_proveedor_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->float('cotizacion');
            $table->string('estado', 50);
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
        Schema::dropIfExists('precarga_comprobante_proveedor');
    }
}
