<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAsiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_asiento_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipoasiento_id');
            $table->foreign('tipoasiento_id', 'fk_asiento_tipoasiento')->references('id')->on('tipoasiento')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numeroasiento');
            $table->date('fecha');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_asiento_venta')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('movimientostock_id')->nullable();
			$table->foreign('movimientostock_id', 'fk_asiento_movimientostock')->references('id')->on('movimientostock')->onDelete('cascade');
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('caja_movimiento_id')->nullable();
            $table->unsignedBigInteger('ordencompra_id')->nullable();
            $table->unsignedBigInteger('recepcionproveedor_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_asiento_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('asiento');
    }
}
