<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CobranzaComprobante extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobranza_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobranza_id');
            $table->foreign('cobranza_id', 'fk_cobranza_comprobante_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('cliente_cuentacorriente_id');
            $table->foreign('cliente_cuentacorriente_id', 'fk_cobranza_comprobante_cliente_cuentacorriente')->references('id')->on('cliente_cuentacorriente')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('montoaplicado', 22, 4);
            $table->float('cotizacion');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cobranza_comprobante_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');            
            $table->softDeletes();
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
        Schema::dropIfExists('cobranza_comprobante');
    }
}
