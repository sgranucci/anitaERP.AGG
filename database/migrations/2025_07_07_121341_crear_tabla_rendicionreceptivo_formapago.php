<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRendicionreceptivoFormapago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rendicionreceptivo_formapago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicionreceptivo_id');
            $table->foreign('rendicionreceptivo_id', 'fk_rendicionreceptivo_formapago_rendicionreceptivo')->references('id')->on('rendicionreceptivo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_rendicionreceptivo_formapago_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_rendicionreceptivo_formapago_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict'); 
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
        Schema::dropIfExists('rendicionreceptivo_formapagos');
    }
}
