<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArbolaprobacionMovimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arbolaprobacion_movimiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('arbolaprobacion_id');
            $table->foreign('arbolaprobacion_id', 'fk_arbolaprobacion_movimiento_arbolaprobacion')->references('id')->on('arbolaprobacion')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fechaenvio');
            $table->unsignedBigInteger('enviousuario_id');
            $table->foreign('enviousuario_id', 'fk_arbolaprobacion_movimiento_envio_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('requisicion_id')->nullable();
            $table->unsignedBigInteger('ordencompra_id')->nullable();
            $table->unsignedBigInteger('solicitudpago_id')->nullable();
            $table->unsignedBigInteger('ordenventa_id')->nullable();
            $table->foreign('ordenventa_id', 'fk_arbolaprobacion_movimiento_ordenventa')->references('id')->on('ordenventa')->onDelete('cascade')->onUpdate('cascade');
            $table->string('hashaprobacion', 255);
            $table->string('hashrechazo', 255);
            $table->string('hashvisualizar', 255)->nullable();
            $table->unsignedBigInteger('nivel');
            $table->unsignedBigInteger('destinatariousuario_id');
            $table->foreign('destinatariousuario_id', 'fk_arbolaprobacion_movimiento_destinatario_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fechaproceso')->nullable();
            $table->string('estado', 50);
            $table->string('observacion', 255);
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
        Schema::dropIfExists('arbolaprobacion_movimiento');
    }
}
