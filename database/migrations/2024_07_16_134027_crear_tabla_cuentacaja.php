<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCuentacaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuentacaja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->string('tipocuenta',1);
            $table->unsignedBigInteger('banco_id')->nullable();
            $table->foreign('banco_id', 'fk_cuentacaja_banco')->references('id')->on('banco')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'fk_cuentacaja_empresa')->references('id')->on('empresa')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_cuentacaja_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cuentacaja_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('cbu',50);
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
        Schema::dropIfExists('cuentacaja');
    }
}
