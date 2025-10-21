<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCliente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->string('contacto',255)->nullable();
            $table->string('fantasia',255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefono',255)->nullable();
            $table->string('urlweb',255)->nullable();
            $table->string('domicilio',255);
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_cliente_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_cliente_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_cliente_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();
			$table->unsignedBigInteger('zonavta_id')->nullable();
            $table->foreign('zonavta_id', 'fk_cliente_zonavta')->references('id')->on('zonavta')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('subzonavta_id')->nullable();
            $table->foreign('subzonavta_id', 'fk_cliente_subzonavta')->references('id')->on('subzonavta')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreign('vendedor_id', 'fk_cliente_vendedor')->references('id')->on('vendedor')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_cliente_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
            $table->string('nroinscripcion',100);
			$table->unsignedBigInteger('condicioniva_id');
            $table->foreign('condicioniva_id', 'fk_cliente_condicioniva')->references('id')->on('condicioniva')->onDelete('restrict')->onUpdate('restrict');
            $table->string('retieneiva',1);
            $table->string('nroiibb',100)->nullable();
            $table->string('condicioniibb',1);
			$table->unsignedBigInteger('condicionventa_id')->nullable();
            $table->foreign('condicionventa_id', 'fk_cliente_condicionventa')->references('id')->on('condicionventa')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('listaprecio_id')->nullable();
            $table->foreign('listaprecio_id', 'fk_cliente_listaprecio')->references('id')->on('listaprecio')->onDelete('set null')->onUpdate('set null');
            $table->float('descuento',5,2)->nullable();
			$table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->foreign('cuentacontable_id', 'fk_cliente_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
            $table->string('vaweb',1);
            $table->string('estado',1);
            $table->string('leyenda',2048)->nullable();
            $table->string('modofacturacion',1);
            $table->string('cajaespecial',1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_cliente_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('cliente');
    }
}
