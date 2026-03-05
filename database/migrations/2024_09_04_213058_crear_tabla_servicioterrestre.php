<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaServicioterrestre extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servicioterrestre', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('codigo',10);
            $table->unsignedBigInteger('tiposervicioterrestre_id');
            $table->foreign('tiposervicioterrestre_id', 'fk_servicioterrestre_tiposervicioterrestre')->references('id')->on('tiposervicioterrestre')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_servicioterrestre_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('observacion',255)->nullable();
            $table->string('abreviatura',10);
            $table->string('ubicacion',1);
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id', 'fk_servicioterrestre_impuesto')->references('id')->on('impuesto')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('precioindividual',22,4);
            $table->decimal('costoconiva',22,4);
            $table->unsignedBigInteger('monedacosto_id');
            $table->foreign('monedacosto_id', 'fk_servicioterrestre_monedacosto')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('modoexento',1);
            $table->decimal('valorexento',22,4);
            $table->float('porcentajeganancia',5,2);
            $table->string('prepago',1);
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
        Schema::dropIfExists('servicioterrestre');
    }
}
