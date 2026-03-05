<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArbolaprobacionNivel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arbolaprobacion_nivel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('arbolaprobacion_id');
            $table->foreign('arbolaprobacion_id', 'fk_arbolaprobacion_nivel_arbolaprobacion')->references('id')->on('arbolaprobacion')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_arbolaprobacion_nivel_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('nivel');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_arbolaprobacion_nivel_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('desdemonto', 22, 4)->nullable();
            $table->decimal('hastamonto', 22, 4)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_arbolaprobacion_nivel_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('arbolaprobacion_nivel');
    }
}
