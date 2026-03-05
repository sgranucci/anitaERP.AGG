<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVenta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('venta', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->date('fecha');
            $table->date('fechajornada');
            $table->unsignedBigInteger('tipotransaccion_id');
            $table->foreign('tipotransaccion_id', 'fk_venta_tipotransaccion')->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_id');
            $table->foreign('puntoventa_id', 'fk_venta_puntoventa')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numerocomprobante');
            $table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_venta_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('condicionventa_id')->nullable();
            $table->foreign('condicionventa_id', 'fk_venta_condicionventa')->references('id')->on('condicionventa')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreign('vendedor_id', 'fk_venta_vendedor')->references('id')->on('vendedor')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_venta_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
            $table->decimal('total',20,6);
            $table->unsignedBigInteger('moneda_id');
			$table->foreign('moneda_id', 'fk_venta_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');
            $table->string('estado', 1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_venta_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->string('leyenda',255)->nullable();
            $table->float('descuento',5,2)->nullable();
            $table->string('descuentointegrado',100)->nullable();
            $table->string('lugarentrega',255)->nullable();
            $table->unsignedBigInteger('cliente_entrega_id')->nullable();
            $table->foreign('cliente_entrega_id', 'fk_venta_cliente_entrega')->references('id')->on('cliente_entrega')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigo',100);  
            $table->string('nombre',255);
            $table->string('domicilio',255);
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_venta_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_venta_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_venta_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();   
            $table->string('email', 255)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('nroinscripcion',100)->nullable();
			$table->unsignedBigInteger('condicioniva_id')->nullable();
            $table->foreign('condicioniva_id', 'fk_venta_condicioniva')->references('id')->on('condicioniva');      
            $table->string('cae',50)->nullable();
            $table->date('fechavencimientocae')->nullable();
            $table->unsignedBigInteger('puntoventaremito_id')->nullable();
            $table->foreign('puntoventaremito_id', 'fk_venta_puntoventaremito')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numeroremito');
            $table->integer('cantidadbulto');
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('venta');
    }
}
