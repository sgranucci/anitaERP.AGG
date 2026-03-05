<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProveedorServicioterrestre extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proveedor_servicioterrestre', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_servicioterrestre_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('servicioterrestre_id');
            $table->foreign('servicioterrestre_id', 'fk_proveedor_servicioterrestre_servicioterrestre')->references('id')->on('servicioterrestre')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('costo',22,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_proveedor_servicioterrestre_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict'); 
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
        Schema::dropIfExists('proveedor_servicioterrestre');
    }
}
