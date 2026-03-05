<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCheque extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cheque', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 1); // E emitido / R recibido
            $table->unsignedBigInteger('chequera_id')->nullable();
            $table->foreign('chequera_id', 'fk_cheque_chequera')->references('id')->on('chequera')->onDelete('restrict')->onUpdate('restrict');
            $table->string('caracter', 1); // O a la orden / N no a la orden
            $table->string('estado', 1);
            $table->date('fechaemision');
            $table->date('fechapago');
            $table->unsignedBigInteger('cuentacaja_id')->nullable();
            $table->foreign('cuentacaja_id', 'fk_cheque_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cheque_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->foreign('caja_id', 'fk_cheque_caja')->references('id')->on('caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('caja_movimiento_id')->nullable();
            $table->foreign('caja_movimiento_id', 'fk_cheque_caja_movimiento')->references('id')->on('caja_movimiento')->onDelete('cascade')->onUpdate('cascade');
            $table->string('numerocheque',50);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cheque_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict'); 
            $table->decimal('monto',22,4);
            $table->decimal('cotizacion',22,4);
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_cheque_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id', 'fk_cheque_cliente')->references('id')->on('cliente')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipodocumento_id')->nullable();
            $table->foreign('tipodocumento_id', 'fk_cheque_tipodocumento')->references('id')->on('tipodocumento')->onDelete('restrict')->onUpdate('restrict');
            $table->string('numerodocumento',100)->nullable();
            $table->string('entregado', 255)->nullable();
            $table->string('anombrede', 255)->nullable();
            $table->unsignedBigInteger('estadocheque_banco_id')->nullable();
            $table->foreign('estadocheque_banco_id', 'fk_cheque_estadocheque_banco')->references('id')->on('estadocheque_banco')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('sucursalpago')->nullable();
            $table->string('tipodistribucion', 1)->nullable();
            $table->unsignedBigInteger('banco_id');
            $table->foreign('banco_id', 'fk_cheque_banco')->references('id')->on('banco')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigopostalbanco', 10)->nullable();
            $table->string('cuentalibradora', 50)->nullable();
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
        Schema::dropIfExists('cheque');    
    }
}
