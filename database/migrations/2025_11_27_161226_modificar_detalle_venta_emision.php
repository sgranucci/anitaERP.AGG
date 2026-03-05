<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModificarDetalleVentaEmision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('venta_emision', function (Blueprint $table) {
            $table->text('detalle')->nullable()->change();
            $table->unsignedBigInteger('lotestock')->nullable()->change();
            $table->unsignedBigInteger('articulo_id')->nullable()->change();
            $table->unsignedBigInteger('combinacion_id')->nullable()->change();
            $table->unsignedBigInteger('talle_id')->nullable()->change();
            $table->unsignedBigInteger('deposito_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('venta_emision', function (Blueprint $table) {
            $table->string('detalle', 255)->nullable()->change();
        });
    }
}
