<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteEntrega extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_entrega', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_entrega_cliente')->references('id')->on('cliente')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre',255);
            $table->string('codigo',20);
            $table->string('domicilio',255);
			$table->unsignedBigInteger('localidad_id')->nullable();
            $table->foreign('localidad_id', 'fk_cliente_entrega_localidad')->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_cliente_entrega_provincia')->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('pais_id');
            $table->foreign('pais_id', 'fk_cliente_entrega_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostal',50)->nullable();
			$table->unsignedBigInteger('zonavta_id')->nullable();
            $table->foreign('zonavta_id', 'fk_cliente_entrega_zonavta')->references('id')->on('zonavta')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('subzonavta_id')->nullable();
            $table->foreign('subzonavta_id', 'fk_cliente_entrega_subzonavta')->references('id')->on('subzonavta')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreign('vendedor_id', 'fk_cliente_entrega_vendedor')->references('id')->on('vendedor')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id', 'fk_cliente_entrega_transporte')->references('id')->on('transporte')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('cliente_entrega');
    }
}
