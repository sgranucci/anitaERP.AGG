<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTablaCajaMovimiento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('caja_movimiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_caja_movimiento_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_caja_id');
            $table->foreign('tipotransaccion_caja_id', 'fk_caja_movimiento_tipotransaccion_caja')->references('id')->on('tipotransaccion_caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numerotransaccion');
            $table->date('fecha');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->foreign('caja_id', 'fk_caja_movimiento_caja')->references('id')->on('caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_caja_movimiento_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id', 'fk_caja_movimiento_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('conceptogasto_id')->nullable();
            $table->foreign('conceptogasto_id', 'fk_caja_movimiento_conceptogasto')->references('id')->on('conceptogasto')->onDelete('restrict')->onUpdate('restrict');
            $table->string('detalle', 255);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_caja_movimiento_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('caja_movimiento');
    }
}
