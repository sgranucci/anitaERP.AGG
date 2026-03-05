<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPrecargaComprobanteProveedorConcepto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('precarga_comprobante_proveedor_concepto', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('precarga_comprobante_proveedor_id');
            $table->foreign('precarga_comprobante_proveedor_id', 'fk_precarga_comprobante_proveedor_concepto_precarga')->references('id')->on('precarga_comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('concepto_ivacompra_id');
            $table->foreign('concepto_ivacompra_id', 'fk_precarga_comprobante_proveedor_concepto_concepto_ivacompra')->references('id')->on('concepto_ivacompra')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('monto', 22, 4);
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
        Schema::dropIfExists('precarga_comprobante_proveedor_concepto');
    }
}
