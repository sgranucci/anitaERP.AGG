<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRendicionreceptivoComision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rendicionreceptivo_comision', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicionreceptivo_id');
            $table->foreign('rendicionreceptivo_id', 'fk_rendicionreceptivo_comision_rendicionreceptivo')->references('id')->on('rendicionreceptivo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('voucher_id');
            $table->foreign('voucher_id', 'fk_rendicionreceptivo_comision_voucher')->references('id')->on('voucher')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_rendicionreceptivo_comision_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_rendicionreceptivo_comision_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict'); 
            $table->decimal('monto',22,4);
            $table->decimal('cotizacion',22,4);  
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
        Schema::dropIfExists('rendicionreceptivo_comision');
    }
}
