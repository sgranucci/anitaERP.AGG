<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCajaMovimientoCuentaCaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('caja_movimiento_cuentacaja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_movimiento_id');
            $table->foreign('caja_movimiento_id', 'fk_caja_movimiento_cuentacaja_caja_movimiento')->references('id')->on('caja_movimiento')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_caja_movimiento_cuentacaja_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('monto',24,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_caja_movimiento_cuentacaja_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion',22,4)->nullable();
            $table->string('observacion', 255)->nullable();
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
        Schema::dropIfExists('caja_movimiento_cuentacaja');
    }
}
