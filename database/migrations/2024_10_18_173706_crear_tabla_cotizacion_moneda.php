<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCotizacionMoneda extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cotizacion_moneda', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cotizacion_id');
            $table->foreign('cotizacion_id', 'fk_cotizacion_moneda_cotizacion')->references('id')->on('cotizacion')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cotizacion_moneda_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacioncompra',22,4)->nullable();
            $table->decimal('cotizacionventa',22,4)->nullable();
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
        Schema::dropIfExists('cotizacion_moneda');
    }
}
