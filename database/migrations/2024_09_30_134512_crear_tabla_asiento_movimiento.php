<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAsientoMovimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asiento_movimiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('asiento_id');
            $table->foreign('asiento_id', 'fk_asiento_movimiento_asiento')->references('id')->on('asiento')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_asiento_movimiento')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->foreign('centrocosto_id', 'fk_asiento_centrocosto')->references('id')->on('centrocosto')->onDelete('set null')->onUpdate('set null');
            $table->decimal('monto',24,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_asiento_movimiento_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('asiento_movimiento');
    }
}
