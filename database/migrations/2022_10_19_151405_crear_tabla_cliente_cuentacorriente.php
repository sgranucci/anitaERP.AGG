<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteCuentacorriente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_cuentacorriente', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->date('fecha');
            $table->date('fechavencimiento');
            $table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id', 'fk_cliente_cuentacorriente_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('total',20,6);
            $table->unsignedBigInteger('moneda_id');
			$table->foreign('moneda_id', 'fk_cliente_cuentacorriente_moneda')->references('id')->on('moneda')->onUpdate('restrict')->onDelete('restrict');            
            $table->unsignedBigInteger('venta_id')->nullable();
			$table->foreign('venta_id', 'fk_cliente_cuentacorriente_venta')->references('id')->on('venta')->onDelete('cascade');
            $table->unsignedBigInteger('cobranza_id')->nullable();
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
        Schema::dropIfExists('cliente_cuentacorriente'); 
    }
}
