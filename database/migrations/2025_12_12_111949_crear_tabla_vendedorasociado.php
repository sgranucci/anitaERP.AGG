<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVendedorasociado extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendedorasociado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendedor_id');
            $table->foreign('vendedor_id', 'fk_vendedorasociado_vendedor')->references('id')->on('vendedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('vendedorasociado_id');
            $table->foreign('vendedorasociado_id', 'fk_vendedorasociado_asociado_vendedor')->references('id')->on('vendedor')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('vendedorasociado');
    }
}
