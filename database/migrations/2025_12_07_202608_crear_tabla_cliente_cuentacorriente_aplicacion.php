<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteCuentacorrienteAplicacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->date('fecha');
            $table->unsignedBigInteger('cliente_cuentacorriente_id');
            $table->foreign('cliente_cuentacorriente_id', 'fk_cliente_cuentacorriente_aplicacion_cliente_cuentacorriente')->references('id')->on('cliente_cuentacorriente')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('total',20,6);
            $table->unsignedBigInteger('moneda_id');
			$table->foreign('moneda_id', 'fk_cliente_cuentacorriente_aplicacion_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');            
            $table->float('cotizacion')->nullable();
            $table->unsignedBigInteger('ventaaplicado_id')->nullable();
			$table->foreign('ventaaplicado_id', 'fk_cliente_cuentacorriente_aplicacion_venta')->references('id')->on('venta')->onDelete('cascade');
            $table->unsignedBigInteger('cobranza_id')->nullable();
            $table->string('comprobanteaplicado', 255)->nullable();
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
        Schema::dropIfExists('cliente_cuentacorriente_aplicacion');
    }
}
