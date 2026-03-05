<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMaterialcapellada extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('materialcapellada', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',50);
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_materialcapellada_articulo')->references('id')->on('articulo')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('materialcapellada');
    }
}
