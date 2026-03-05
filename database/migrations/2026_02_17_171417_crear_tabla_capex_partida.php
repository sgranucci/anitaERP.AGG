<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCapexPartida extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capex_partida', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('capex_id');
            $table->foreign('capex_id', 'fk_capex_partida_capex')->references('id')->on('capex')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre', 255);
			$table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_capex_partida_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_capex_partida_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('estado', 50)->nullable();
            $table->string('codigo', 50);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_capex_partida_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('capex_partida');
    }
}
